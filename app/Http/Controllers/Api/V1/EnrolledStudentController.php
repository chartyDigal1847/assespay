<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TuitionRecord;
use App\Services\BalanceService;
use App\Services\BillingAccountService;
use App\Services\DeorisStudentDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnrolledStudentController extends Controller
{
    public function __construct(
        protected DeorisStudentDirectory $students,
        protected BillingAccountService $accounts,
        protected BalanceService $balances,
    ) {}

    public function index()
    {
        $items = collect($this->students->eligibleStudents())
            ->map(fn ($student) => $this->withLocalState($student))
            ->values();

        return response()->json(['data' => $items]);
    }

    public function storeAssessment(Request $request)
    {
        $data = $request->validate([
            'source' => 'required|string|in:deoris',
            'source_id' => 'required|string|max:100',
            'school_year' => 'required|string|max:20',
            'term' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
            'tuition_amount' => 'nullable|numeric|min:0',
            'misc_amount' => 'nullable|numeric|min:0',
            'other_amount' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
        ]);

        $deorisStudent = $this->students->findEligible($data['source_id']);

        if (! $deorisStudent) {
            return response()->json(['message' => 'This student is not approved and enrolled in DEORIS.'], 422);
        }

        $tuition = (float) ($data['tuition_amount'] ?? 0);
        $misc = (float) ($data['misc_amount'] ?? 0);
        $other = (float) ($data['other_amount'] ?? 0);

        if (($tuition + $misc + $other) <= 0) {
            return response()->json(['message' => 'Enter an amount before creating the payable.'], 422);
        }

        $result = DB::transaction(function () use ($data, $deorisStudent, $tuition, $misc, $other) {
            $studentNumber = $this->students->studentNumberFor($deorisStudent);
            $email = strtolower((string) ($deorisStudent['email'] ?? ''));
            $student = Student::withTrashed()
                ->where('portal_user_id', $deorisStudent['id'])
                ->when($email !== '', fn ($query) => $query->orWhereRaw('LOWER(email) = ?', [$email]))
                ->orWhere('student_id', $studentNumber)
                ->first();

            $studentData = [
                'portal_user_id' => $deorisStudent['id'],
                'student_id' => $studentNumber,
                'name' => $deorisStudent['name'],
                'email' => $email ?: null,
                'program' => $deorisStudent['program'] ?? 'Enrolled',
                'year_level' => $deorisStudent['grade_level'] ?? '—',
                'status' => 'active',
            ];

            if ($student) {
                if ($student->trashed()) {
                    $student->restore();
                }

                $student->fill($studentData)->save();
            } else {
                $student = Student::create($studentData);
            }

            $account = $this->accounts->ensureForStudent($student);
            $schoolYear = $data['school_year'] ?: now()->year.'-'.now()->addYear()->year;
            $description = $data['description'] ?: 'Tuition assessment - '.$schoolYear;

            $record = TuitionRecord::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'billing_account_id' => $account->id,
                    'school_year' => $schoolYear,
                    'term' => $data['term'] ?? null,
                    'description' => $description,
                ],
                [
                    'tuition_amount' => $tuition,
                    'misc_amount' => $misc,
                    'other_amount' => $other,
                    'total_amount' => $tuition + $misc + $other,
                    'status' => 'pending',
                    'due_date' => $data['due_date'] ?? null,
                ]
            );

            $this->balances->recalculate($account);

            return [
                'student' => $student->fresh(),
                'billing_record' => $record->fresh(),
                'balance' => $student->balance()->first(),
            ];
        });

        return response()->json(['data' => $result], 201);
    }

    protected function withLocalState(array $deorisStudent): array
    {
        $studentNumber = $this->students->studentNumberFor($deorisStudent);
        $student = Student::where('portal_user_id', $deorisStudent['id'])
            ->orWhere(fn ($q) => $deorisStudent['email'] ? $q->whereRaw('LOWER(email) = ?', [$deorisStudent['email']]) : $q->whereRaw('1 = 0'))
            ->first();

        return [
            ...$deorisStudent,
            'student_id_number' => $studentNumber,
            'local_student_id' => $student?->id,
            'label' => $deorisStudent['name'].' - '.($deorisStudent['student_number'] ?: $deorisStudent['email']),
        ];
    }
}
