<?php

namespace App\Listeners;

use App\Events\ReceiptGenerated;
use App\Jobs\SendNotificationJob;

class NotifyOnReceiptGenerated
{
    public function handle(ReceiptGenerated $event): void
    {
        $receipt = $event->receipt;
        $payment = $event->payment;
        $portalId = (string) $payment->student?->portal_user_id;

        if (! $portalId) {
            return;
        }

        SendNotificationJob::dispatch(
            $portalId,
            'receipt_generated',
            'Official Receipt Issued',
            'Receipt '.$receipt->receipt_number.' for ₱'.number_format($receipt->amount, 2).' is now available.',
            [
                'receipt_id'     => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'amount'         => (float) $receipt->amount,
            ],
        )->onQueue(config('assesspay.queues.notifications'));
    }
}
