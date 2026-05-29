<?php

namespace App\Events;

use App\Models\BillingAccount;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FinancialRecordUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $type,
        public readonly BillingAccount $billingAccount,
    ) {}
}
