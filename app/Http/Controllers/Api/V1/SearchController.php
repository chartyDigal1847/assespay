<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OfficialReceipt;
use App\Models\Payment;
use App\Models\Student;
use App\Models\TuitionRecord;
use App\Support\PortalSession;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'type' => 'nullable|in:payments,receipts,billing,students,all',
        ]);

        $q = $request->input('q');
        $type = $request->input('type', 'all');
        $studentScopeId = null;
        if (PortalSession::role($request) === 'student') {
            $studentScopeId = PortalSession::studentId($request);
            abort_unless($studentScopeId, 403, 'Student account is not available.');
        }
        $results = [];

        if (in_array($type, ['payments', 'all'], true)) {
            $results['payments'] = Payment::with('student')
                ->when($studentScopeId, fn ($query) => $query->where('student_id', $studentScopeId))
                ->where(function ($query) use ($q) {
                    $query->where('receipt_number', 'like', "%{$q}%")
                        ->orWhere('reference_number', 'like', "%{$q}%")
                        ->orWhereHas('student', fn ($student) => $student
                            ->where('name', 'like', "%{$q}%")
                            ->orWhere('student_id', 'like', "%{$q}%"));
                })
                ->limit(10)
                ->get();
        }

        if (in_array($type, ['receipts', 'all'], true)) {
            $results['receipts'] = OfficialReceipt::query()
                ->when($studentScopeId, fn ($query) => $query->where('student_id', $studentScopeId))
                ->where('receipt_number', 'like', "%{$q}%")
                ->limit(10)
                ->get();
        }

        if (in_array($type, ['billing', 'all'], true)) {
            $results['billing'] = TuitionRecord::query()
                ->when($studentScopeId, fn ($query) => $query->where('student_id', $studentScopeId))
                ->where(function ($query) use ($q) {
                    $query->where('description', 'like', "%{$q}%")
                        ->orWhere('school_year', 'like', "%{$q}%");
                })
                ->limit(10)
                ->get();
        }

        if (in_array($type, ['students', 'all'], true)) {
            $results['students'] = Student::query()
                ->when($studentScopeId, fn ($query) => $query->where('id', $studentScopeId))
                ->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('student_id', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                })
                ->limit(10)
                ->get();
        }

        return response()->json(['query' => $q, 'results' => $results]);
    }
}
