<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\ReceiptService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReceiptJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $paymentId,
        public string $cashierPortalId,
    ) {}

    public function handle(ReceiptService $receipts): void
    {
        $payment = Payment::find($this->paymentId);
        if ($payment && ! $payment->officialReceipt) {
            $receipts->issue($payment, $this->cashierPortalId);
        }
    }
}
