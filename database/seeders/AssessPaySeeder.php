<?php

namespace Database\Seeders;

use App\Models\Balance;
use App\Models\BillingAccount;
use App\Models\PaymentMethod;
use App\Models\Student;
use App\Models\TuitionRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AssessPaySeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['code' => 'cash', 'name' => 'Cash'],
            ['code' => 'bank', 'name' => 'Bank Transfer'],
            ['code' => 'online', 'name' => 'Online Payment'],
        ];

        foreach ($methods as $m) {
            PaymentMethod::firstOrCreate(['code' => $m['code']], $m);
        }

        $student = Student::firstOrCreate(
            ['student_id' => 'DEMO-2026-001'],
            [
                'name' => 'Demo Student',
                'program' => 'BS Computer Science',
                'year_level' => '2nd Year',
                'email' => 'demo.student@deoris.test',
                'status' => 'active',
            ]
        );

        $account = BillingAccount::firstOrCreate(
            ['student_id' => $student->id],
            [
                'account_number' => 'AP-DEMO-001',
                'currency' => 'PHP',
                'status' => 'active',
            ]
        );

        Balance::firstOrCreate(
            ['billing_account_id' => $account->id],
            [
                'student_id' => $student->id,
                'total_assessed' => 45000,
                'total_paid' => 15000,
                'current_balance' => 30000,
                'last_recalculated_at' => now(),
            ]
        );

        TuitionRecord::firstOrCreate(
            [
                'billing_account_id' => $account->id,
                'school_year' => '2025-2026',
                'description' => 'Tuition — 2nd Semester',
            ],
            [
                'student_id' => $student->id,
                'term' => '2nd',
                'tuition_amount' => 40000,
                'misc_amount' => 5000,
                'other_amount' => 0,
                'total_amount' => 45000,
                'status' => 'partially_paid',
                'due_date' => now()->addMonths(2),
            ]
        );
    }
}
