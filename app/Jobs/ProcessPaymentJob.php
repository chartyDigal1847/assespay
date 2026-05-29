<?php

namespace App\Jobs;

use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaymentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $paymentId,
        public string $cashierPortalId,
    ) {}

    public function handle(PaymentService $payments): void
    {
        $payment = \App\Models\Payment::find($this->paymentId);
        if ($payment) {
            $payments->confirm($payment, $this->cashierPortalId);
        }
    }
}
