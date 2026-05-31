<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClearanceController extends Controller
{
    public function show(Request $request, string $studentNumber)
    {
        $student = $this->findStudent($studentNumber);
        abort_if(! $student, 404, 'Student billing record not found.');

        return response()->json($this->clearancePayload($student));
    }

    public function showForClearCheck(Request $request, string $studentNumber)
    {
        if (! $this->hasValidServiceKey($request)) {
            return response()->json([
                'message' => 'Invalid service key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $student = $this->findStudent($studentNumber);

        if (! $student) {
            return response()->json([
                'cleared' => false,
                'module' => 'assesspay',
                'student_id' => $studentNumber,
                'current_balance' => null,
                'unresolved_issues' => 'No AssessPay billing record found for this student.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json($this->clearancePayload($student));
    }

    private function findStudent(string $studentNumber): ?Student
    {
        $portalId = $this->portalIdFromClearCheckRegNo($studentNumber);

        return Student::query()
            ->where('student_id', $studentNumber)
            ->orWhere('portal_user_id', $studentNumber)
            ->when($portalId !== null, function ($query) use ($portalId) {
                $query->orWhere('portal_user_id', $portalId)
                    ->orWhere('student_id', 'DEORIS-'.$portalId);
            })
            ->first();
    }

    private function clearancePayload(Student $student): array
    {
        $balance = (float) ($student->balance?->current_balance ?? 0);
        $cleared = $balance <= 0;

        return [
            'cleared' => $cleared,
            'module' => 'assesspay',
            'student_id' => $student->student_id,
            'portal_user_id' => $student->portal_user_id,
            'current_balance' => $balance,
            'local_cleared' => $cleared,
            'can_complete' => $cleared,
            'unresolved_issues' => $cleared ? null : 'Outstanding AssessPay balance: PHP '.number_format($balance, 2),
            'validated_at' => now()->toIso8601String(),
        ];
    }

    private function portalIdFromClearCheckRegNo(string $studentNumber): ?string
    {
        if (! preg_match('/^STU-(\d+)$/i', $studentNumber, $matches)) {
            return null;
        }

        return (string) ((int) $matches[1]);
    }

    private function hasValidServiceKey(Request $request): bool
    {
        $expected = (string) config('assesspay.clearcheck.service_key');
        $provided = (string) $request->header('X-Service-Key', '');

        return $expected !== '' && hash_equals($expected, $provided);
    }
}
