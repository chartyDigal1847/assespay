<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Events\PaymentConfirmed as PaymentConfirmedEvent;
use App\Events\TuitionPaid as TuitionPaidEvent;
use App\Models\Payment;
use App\Models\PaymentAuditLog;
use App\Models\Student;
use App\Models\TuitionRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected BillingAccountService $billingAccounts,
        protected BalanceService $balances,
        protected ReceiptService $receipts,
        protected EventHubPublisher $events,
        protected ActivityLogger $activity,
    ) {}

    public function submit(array $data, string $submitterPortalId, string $role): Payment
    {
        return DB::transaction(function () use ($data, $submitterPortalId, $role) {
            $student = Student::findOrFail($data['student_id']);
            $account = $this->billingAccounts->ensureForStudent($student);
            $tuitionRecord = null;

            if (! empty($data['tuition_record_id'])) {
                $tuitionRecord = TuitionRecord::where('student_id', $student->id)
                    ->where('billing_account_id', $account->id)
                    ->findOrFail($data['tuition_record_id']);

                $paid = (float) $tuitionRecord->payments()
                    ->where('status', PaymentStatus::Paid)
                    ->sum('amount');
                $remaining = max(0, (float) $tuitionRecord->total_amount - $paid);
                $requestedCents = (int) round(((float) $data['amount']) * 100);
                $remainingCents = (int) round($remaining * 100);

                if ($requestedCents > $remainingCents) {
                    throw ValidationException::withMessages([
                        'amount' => 'Payment amount exceeds the selected payable balance.',
                    ]);
                }

            }

            $payment = Payment::create([
                'student_id' => $student->id,
                'billing_account_id' => $account->id,
                'tuition_record_id' => $tuitionRecord?->id,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'amount' => $data['amount'],
                'method' => $data['method'] ?? 'cash',
                'reference_number' => $data['reference_number'] ?? null,
                'status' => PaymentStatus::Pending,
                'submitted_by_portal_id' => $submitterPortalId,
                'correlation_id' => $data['correlation_id'] ?? (string) Str::uuid(),
            ]);

            $this->audit($payment, 'payment.created', null, $payment->toArray());
            $this->activity->log('payment.created', Payment::class, $payment->id);

            return $payment;
        });
    }

    public function confirm(Payment $payment, string $cashierPortalId): Payment
    {
        if ($payment->status === PaymentStatus::Paid) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $cashierPortalId) {
            $before = $payment->toArray();

            $payment->update([
                'status' => PaymentStatus::Paid,
                'confirmed_by_portal_id' => $cashierPortalId,
                'confirmed_at' => now(),
                'paid_at' => now(),
            ]);

            $this->updateTuitionRecordStatus($payment);
            $this->balances->recalculate($payment->billingAccount);
            $receipt = $this->receipts->issue($payment, $cashierPortalId);

            $this->audit($payment, 'payment.confirmed', $before, $payment->fresh()->toArray(), $cashierPortalId);
            $this->activity->log('payment.confirmed', Payment::class, $payment->id);

            $this->events->publish('PaymentConfirmed', [
                'payment_id' => $payment->id,
                'student_id' => $payment->student_id,
                'amount' => (float) $payment->amount,
            ]);

            $this->events->publish('TuitionPaid', [
                'payment_id' => $payment->id,
                'receipt_number' => $receipt->receipt_number,
                'student_id' => $payment->student_id,
                'amount' => (float) $payment->amount,
            ]);

            $fresh = $payment->fresh(['officialReceipt', 'student', 'billingAccount']);
            if (config('assesspay.realtime.enabled')) {
                event(new TuitionPaidEvent($fresh));
                event(new PaymentConfirmedEvent($fresh));
            }

            return $payment->fresh(['officialReceipt', 'student', 'billingAccount']);
        });
    }

    protected function updateTuitionRecordStatus(Payment $payment): void
    {
        $record = $payment->tuitionRecord;

        if (! $record) {
            return;
        }

        $paid = (float) $record->payments()
            ->where('status', PaymentStatus::Paid)
            ->sum('amount');
        $total = (float) $record->total_amount;

        $record->update([
            'status' => match (true) {
                $paid <= 0 => PaymentStatus::Pending,
                $paid >= $total => PaymentStatus::Paid,
                default => PaymentStatus::PartiallyPaid,
            },
        ]);
    }

    public function reverse(Payment $payment, string $cashierPortalId, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $cashierPortalId, $reason) {
            $before = $payment->toArray();

            $payment->update(['status' => PaymentStatus::Refunded]);
            $this->updateTuitionRecordStatus($payment);
            $this->balances->recalculate($payment->billingAccount);

            $this->audit($payment, 'payment.reversed', $before, $payment->fresh()->toArray(), $cashierPortalId, ['reason' => $reason]);
            $this->activity->log('payment.reversed', Payment::class, $payment->id, ['reason' => $reason]);

            return $payment->fresh();
        });
    }

    protected function audit(
        Payment $payment,
        string $action,
        ?array $before,
        ?array $after,
        ?string $actorPortalId = null,
        array $extra = [],
    ): void {
        PaymentAuditLog::create([
            'payment_id' => $payment->id,
            'action' => $action,
            'actor_portal_id' => $actorPortalId ?? session('assesspay_portal_id'),
            'actor_role' => session('assesspay_role'),
            'before_state' => $before ? array_merge($before, $extra) : ($extra ?: null),
            'after_state' => $after,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->header('User-Agent'),
        ]);
    }
}
