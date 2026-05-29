<?php

namespace App\Services;

use App\Events\ReceiptGenerated as ReceiptGeneratedEvent;
use App\Jobs\GenerateReceiptJob;
use App\Models\OfficialReceipt;
use App\Models\Payment;
use Illuminate\Support\Str;

class ReceiptService
{
    public function __construct(
        protected EventHubPublisher $events,
        protected ActivityLogger $activity,
    ) {}

    public function issue(Payment $payment, string $cashierPortalId): OfficialReceipt
    {
        $receiptNumber = 'OR-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));

        $receipt = OfficialReceipt::create([
            'payment_id' => $payment->id,
            'student_id' => $payment->student_id,
            'receipt_number' => $receiptNumber,
            'amount' => $payment->amount,
            'issued_by_portal_id' => $cashierPortalId,
            'issued_at' => now(),
            'metadata' => [
                'method' => $payment->method,
                'reference_number' => $payment->reference_number,
            ],
        ]);

        $payment->update(['receipt_number' => $receiptNumber]);

        $this->events->publish('ReceiptGenerated', [
            'receipt_id' => $receipt->id,
            'receipt_number' => $receiptNumber,
            'payment_id' => $payment->id,
            'student_id' => $payment->student_id,
            'amount' => (float) $payment->amount,
        ]);

        $this->activity->log('receipt.generated', OfficialReceipt::class, $receipt->id);

        if (config('assesspay.realtime.enabled')) {
            event(new ReceiptGeneratedEvent($receipt, $payment));
        }

        return $receipt;
    }

    public function queueGeneration(Payment $payment, string $cashierPortalId): void
    {
        GenerateReceiptJob::dispatch($payment->id, $cashierPortalId)
            ->onQueue(config('assesspay.queues.billing'));
    }
}
