<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillingAccount;
use App\Models\TuitionRecord;
use App\Services\BillingAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingRecordController extends Controller
{
    public function __construct(protected BillingAccountService $accounts) {}

    public function index(Request $request)
    {
        $records = TuitionRecord::with(['student', 'billingAccount'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->student_id))
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

    public function show(TuitionRecord $billingRecord)
    {
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
        $accounts = BillingAccount::with(['student', 'balance'])
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->student_id))
            ->paginate($request->integer('per_page', 15));

        return response()->json(['data' => $accounts]);
    }
}
