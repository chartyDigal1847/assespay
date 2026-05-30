<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillingAccount;
use App\Models\TuitionRecord;
use App\Services\BillingAccountService;
use App\Support\PortalSession;
use Illuminate\Http\Request;

class BillingRecordController extends Controller
{
    public function __construct(protected BillingAccountService $accounts) {}

    public function index(Request $request)
    {
        $studentId = $this->studentScopeId($request);
        $records = TuitionRecord::with(['student', 'billingAccount'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->student_id))
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['data' => $records]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'school_year' => 'required|string|max:20',
            'term' => 'nullable|string|max:20',
            'description' => 'required|string|max:255',
            'tuition_amount' => 'numeric|min:0',
            'misc_amount' => 'numeric|min:0',
            'other_amount' => 'numeric|min:0',
            'due_date' => 'nullable|date',
        ]);

        $student = \App\Models\Student::findOrFail($data['student_id']);
        $account = $this->accounts->ensureForStudent($student);

        $total = ($data['tuition_amount'] ?? 0) + ($data['misc_amount'] ?? 0) + ($data['other_amount'] ?? 0);

        $record = TuitionRecord::create([
            ...$data,
            'billing_account_id' => $account->id,
            'total_amount' => $total,
            'status' => 'pending',
        ]);

        app(\App\Services\BalanceService::class)->recalculate($account);

        return response()->json(['data' => $record], 201);
    }

    public function show(Request $request, TuitionRecord $billingRecord)
    {
        $this->ensureVisibleToSession($request, $billingRecord->student_id);

        return response()->json(['data' => $billingRecord->load(['student', 'billingAccount'])]);
    }

    public function update(Request $request, TuitionRecord $billingRecord)
    {
        $data = $request->validate([
            'description' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:pending,processing,paid,partially_paid,overdue,cancelled,refunded',
            'due_date' => 'sometimes|nullable|date',
        ]);

        $billingRecord->update($data);

        return response()->json(['data' => $billingRecord->fresh()]);
    }

    public function destroy(TuitionRecord $billingRecord)
    {
        $billingRecord->delete();

        return response()->json(['success' => true]);
    }

    public function accounts(Request $request)
    {
        $studentId = $this->studentScopeId($request);
        $accounts = BillingAccount::with(['student', 'balance'])
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->student_id))
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->paginate($request->integer('per_page', 15));

        return response()->json(['data' => $accounts]);
    }

    protected function studentScopeId(Request $request): ?int
    {
        if (PortalSession::role($request) !== 'student') {
            return null;
        }

        $studentId = PortalSession::studentId($request);
        abort_unless($studentId, 403, 'Student account is not available.');

        return $studentId;
    }

    protected function ensureVisibleToSession(Request $request, int $studentId): void
    {
        if (PortalSession::role($request) !== 'student') {
            return;
        }

        abort_unless((int) PortalSession::studentId($request) === $studentId, 404);
    }
}
