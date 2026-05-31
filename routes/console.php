<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('assesspay:sync-clearance', function () {
    $accounts = \App\Models\BillingAccount::query()->with('student')->get();
    $balances = app(\App\Services\BalanceService::class);

    $accounts->each(function (\App\Models\BillingAccount $account) use ($balances) {
        $balances->recalculate($account);
    });

    $this->info("Synced clearance state for {$accounts->count()} billing account(s).");
})->purpose('Recalculate AssessPay balances and sync DEORIS clearance unlock state');
