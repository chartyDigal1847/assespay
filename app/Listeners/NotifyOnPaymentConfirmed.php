<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Jobs\SendNotificationJob;

class NotifyOnPaymentConfirmed
{
    public function handle(PaymentConfirmed $event): void
    {
        $payment = $event->payment;
        $portalId = (string) $payment->student?->portal_user_id;

        if (! $portalId) {
            return;
        }

        SendNotificationJob::dispatch(
            $portalId,
            'payment_confirmed',
            'Payment Confirmed',
            'Your payment of ₱'.number_format($payment->amount, 2).' has been confirmed.',
            [
                'payment_id'     => $payment->id,
                'amount'         => (float) $payment->amount,
                'receipt_number' => $payment->receipt_number,
            ],
        )->onQueue(config('assesspay.queues.notifications'));
    }
}
