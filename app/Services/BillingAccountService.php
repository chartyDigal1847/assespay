<?php

namespace App\Services;

use App\Models\Balance;
use App\Models\BillingAccount;
use App\Models\Student;
use Illuminate\Support\Str;

class BillingAccountService
{
    public function ensureForStudent(Student $student): BillingAccount
    {
        $account = $student->billingAccounts()->where('status', 'active')->first();

        if ($account) {
            return $account;
        }

        $account = BillingAccount::create([
            'student_id' => $student->id,
            'account_number' => 'AP-'.strtoupper(Str::random(8)),
            'currency' => 'PHP',
            'status' => 'active',
        ]);

        Balance::create([
            'billing_account_id' => $account->id,
            'student_id' => $student->id,
            'total_assessed' => 0,
            'total_paid' => 0,
            'current_balance' => 0,
            'last_recalculated_at' => now(),
        ]);

        return $account;
    }
}
