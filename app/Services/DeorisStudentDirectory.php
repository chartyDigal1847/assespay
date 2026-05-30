<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DeorisStudentDirectory
{
    public function eligibleStudents(): array
    {
        return $this->safeDeoris(function () {
            return DB::connection('deoris')
                ->table('users')
                ->select([
                    'id',
                    'name',
                    'email',
                    'student_number',
                    'admission_status',
                    'enrollment_status',
                ])
                ->where('role', 'student')
                ->where('admission_status', 'approved')
                ->where('enrollment_status', 'enrolled')
                ->orderBy('name')
                ->get()
                ->map(fn ($user) => $this->withEnrollment($this->normalize((array) $user)))
                ->all();
        }, []);
    }

    public function findEligible(string|int $id): ?array
    {
        return $this->safeDeoris(function () use ($id) {
            $user = DB::connection('deoris')
                ->table('users')
                ->select([
                    'id',
                    'name',
                    'email',
                    'student_number',
                    'admission_status',
                    'enrollment_status',
                ])
                ->where('id', $id)
                ->where('role', 'student')
                ->where('admission_status', 'approved')
                ->where('enrollment_status', 'enrolled')
                ->first();

            return $user ? $this->withEnrollment($this->normalize((array) $user)) : null;
        });
    }

    public function studentNumberFor(array $student): string
    {
        return $student['student_number'] ?: 'DEORIS-'.$student['id'];
    }

    protected function normalize(array $user): array
    {
        return [
            'source' => 'deoris',
            'id' => (string) $user['id'],
            'name' => (string) $user['name'],
            'email' => strtolower((string) $user['email']),
            'student_number' => (string) ($user['student_number'] ?? ''),
            'admission_status' => (string) $user['admission_status'],
            'enrollment_status' => (string) $user['enrollment_status'],
        ];
    }

    public function schoolYears(): array
    {
        $years = collect();

        if ($this->hasEnrolleaseTable('enrollments')) {
            $years = $years->merge(
                DB::connection('enrollease')
                    ->table('enrollments')
                    ->whereNotNull('school_year')
                    ->where('school_year', '!=', '')
                    ->distinct()
                    ->pluck('school_year')
            );
        }

        if ($this->hasEnrolleaseTable('academic_terms')) {
            $years = $years->merge(
                DB::connection('enrollease')
                    ->table('academic_terms')
                    ->whereNotNull('school_year')
                    ->where('school_year', '!=', '')
                    ->distinct()
                    ->pluck('school_year')
            );
        }

        $years = $years->merge(
            DB::table('tuition_records')
                ->whereNotNull('school_year')
                ->where('school_year', '!=', '')
                ->distinct()
                ->pluck('school_year')
        );

        $unique = $years
            ->map(fn ($year) => $this->normalizeSchoolYear((string) $year))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($unique !== []) {
            return $unique;
        }

        $year = (int) now()->format('Y');

        return [$year.'-'.($year + 1)];
    }

    public function termsBySchoolYear(): array
    {
        if (! $this->hasEnrolleaseTable('academic_terms')) {
            return [];
        }

        return $this->safeEnrollease(function () {
            return DB::connection('enrollease')
                ->table('academic_terms')
                ->select('school_year', 'semester')
                ->whereNotNull('school_year')
                ->whereNotNull('semester')
                ->orderBy('school_year')
                ->orderByRaw("FIELD(semester, '1st', '2nd', 'summer')")
                ->get()
                ->groupBy(fn ($term) => $this->normalizeSchoolYear((string) $term->school_year))
                ->map(fn ($items) => $items->pluck('semester')->unique()->values()->all())
                ->all();
        }, []);
    }

    protected function withEnrollment(array $student): array
    {
        $enrollment = $this->latestEnrollmentFor($student);
        $schoolYear = $this->normalizeSchoolYear((string) ($enrollment->school_year ?? ''));
        $gradeLevel = $enrollment?->grade_level ? (string) $enrollment->grade_level : '';

        return [
            ...$student,
            'enrollease_enrollment_id' => $enrollment?->id ? (string) $enrollment->id : '',
            'school_year' => $schoolYear,
            'grade_level' => $gradeLevel,
            'program' => $gradeLevel ? 'Grade '.$gradeLevel : 'Enrolled',
            'lrn' => (string) ($enrollment->lrn ?? $student['student_number'] ?? ''),
        ];
    }

    protected function latestEnrollmentFor(array $student): ?object
    {
        if (! $this->hasEnrolleaseTable('enrollments')) {
            return null;
        }

        $query = DB::connection('enrollease')
            ->table('enrollments as e')
            ->leftJoin('students as s', 's.id', '=', 'e.student_id')
            ->select('e.*', 's.deoris_user_id')
            ->where('e.status', 'enrolled')
            ->where(function ($builder) use ($student) {
                $builder->where('s.deoris_user_id', $student['id']);

                if ($student['email'] !== '') {
                    $builder->orWhereRaw('LOWER(e.email) = ?', [$student['email']]);
                }

                if ($student['student_number'] !== '') {
                    $builder->orWhere('e.lrn', $student['student_number']);
                }
            })
            ->orderByDesc('e.updated_at')
            ->orderByDesc('e.id');

        try {
            return $query->first();
        } catch (\Throwable $e) {
            Log::warning('[AssessPay] EnrollEase enrollment lookup failed', [
                'message' => $e->getMessage(),
                'student_id' => $student['id'] ?? null,
            ]);

            return null;
        }
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T|null
     */
    protected function safeDeoris(callable $callback, mixed $default = null): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::warning('[AssessPay] DEORIS student directory unavailable', [
                'message' => $e->getMessage(),
            ]);

            return $default;
        }
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    protected function safeEnrollease(callable $callback, mixed $default = []): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::warning('[AssessPay] EnrollEase directory unavailable', [
                'message' => $e->getMessage(),
            ]);

            return $default;
        }
    }

    protected function hasEnrolleaseTable(string $table): bool
    {
        try {
            return Schema::connection('enrollease')->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function normalizeSchoolYear(string $schoolYear): string
    {
        return trim(str_replace(['–', '—'], '-', $schoolYear));
    }
}
