<?php

namespace App\Services;

use App\Events\BalanceUpdated as BalanceUpdatedEvent;
use App\Events\FinancialRecordUpdated as FinancialRecordUpdatedEvent;
use App\Models\Balance;
use App\Models\BillingAccount;
use App\Models\FinancialTransaction;
use App\Services\EventHubPublisher;

class BalanceService
{
    public function __construct(
        protected EventHubPublisher $events,
        protected ActivityLogger $activity,
        protected DeorisClearanceSync $clearanceSync,
    ) {}

    public function recalculate(BillingAccount $account): Balance
    {
        $balance = $account->balance ?? Balance::firstOrCreate(
            ['billing_account_id' => $account->id],
            ['student_id' => $account->student_id]
        );

        $assessed = $account->tuitionRecords()->sum('total_amount');
        $paid = $account->payments()->where('status', 'paid')->sum('amount');

        $balance->update([
            'total_assessed' => $assessed,
            'total_paid' => $paid,
            'current_balance' => max(0, $assessed - $paid),
            'last_recalculated_at' => now(),
        ]);

        $this->events->publish('BalanceUpdated', [
            'billing_account_id' => $account->id,
            'student_id' => $account->student_id,
            'current_balance' => (float) $balance->current_balance,
        ]);

        if ($account->student) {
            $this->clearanceSync->sync($account->student, $balance->fresh());
        }

        if (config('assesspay.realtime.enabled')) {
            event(new BalanceUpdatedEvent($account, $balance->fresh()));
        }

        return $balance->fresh();
    }

    public function adjustByCashier(
        BillingAccount $account,
        float $newBalance,
        string $portalUserId,
        string $reason,
    ): Balance {
        $balance = $this->recalculate($account);
        $before = (float) $balance->current_balance;

        $balance->update([
            'current_balance' => $newBalance,
            'last_recalculated_at' => now(),
        ]);

        if ($account->student) {
            $this->clearanceSync->sync($account->student, $balance->fresh());
        }

        FinancialTransaction::create([
            'billing_account_id' => $account->id,
            'student_id' => $account->student_id,
            'transaction_type' => 'balance_adjustment',
            'debit' => $newBalance > $before ? $newBalance - $before : 0,
            'credit' => $before > $newBalance ? $before - $newBalance : 0,
            'running_balance' => $newBalance,
            'reference' => $reason,
            'performed_by_portal_id' => $portalUserId,
            'role' => 'cashier',
        ]);

        $this->activity->log('balance.modified', Balance::class, $balance->id, [
            'before' => $before,
            'after' => $newBalance,
            'reason' => $reason,
        ]);

        $this->events->publish('FinancialRecordUpdated', [
            'type' => 'balance_adjustment',
            'billing_account_id' => $account->id,
            'student_id' => $account->student_id,
        ]);

        if (config('assesspay.realtime.enabled')) {
            event(new FinancialRecordUpdatedEvent('balance_adjustment', $account));
        }

        return $balance->fresh();
    }
}
