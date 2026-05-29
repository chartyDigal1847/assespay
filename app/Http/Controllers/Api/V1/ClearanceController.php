<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\ClearCheckClient;
use Illuminate\Http\Request;

class ClearanceController extends Controller
{
    public function __construct(protected ClearCheckClient $clearCheck) {}

    public function show(Request $request, string $studentNumber)
    {
        $student = Student::where('student_id', $studentNumber)->firstOrFail();
        $balance = (float) ($student->balance?->current_balance ?? 0);

        $external = $this->clearCheck->studentCleared($studentNumber);
        $localCleared = $this->clearCheck->canCompleteAcademically($balance);

        return response()->json([
            'student_id' => $student->student_id,
            'current_balance' => $balance,
            'local_cleared' => $localCleared,
            'clearcheck' => $external,
            'can_complete' => $localCleared && ($external['cleared'] ?? true),
        ]);
    }
}
