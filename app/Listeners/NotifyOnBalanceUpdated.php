<?php

namespace App\Listeners;

use App\Events\BalanceUpdated;
use App\Jobs\SendNotificationJob;

class NotifyOnBalanceUpdated
{
    public function handle(BalanceUpdated $event): void
    {
        $account = $event->billingAccount;
        $balance = $event->balance;
        $portalId = (string) $account->student?->portal_user_id;

        if (! $portalId) {
            return;
        }

        SendNotificationJob::dispatch(
            $portalId,
            'balance_updated',
            'Balance Updated',
            'Your current balance is ₱'.number_format($balance->current_balance, 2).'.',
            [
                'billing_account_id' => $account->id,
                'current_balance'    => (float) $balance->current_balance,
            ],
        )->onQueue(config('assesspay.queues.notifications'));
    }
}
