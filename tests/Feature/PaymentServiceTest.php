<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\OfficialReceipt;
use App\Models\Payment;
use App\Models\Student;
use App\Models\TuitionRecord;
use App\Services\BalanceService;
use App\Services\BillingAccountService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_payment_for_payable_becomes_paid_when_processed(): void
    {
        $student = Student::create([
            'portal_user_id' => '2',
            'student_id' => 'DEORIS-2',
            'name' => 'Student',
            'program' => 'Grade 7',
            'year_level' => '7',
            'email' => 'student@example.com',
            'status' => 'active',
        ]);
        $account = app(BillingAccountService::class)->ensureForStudent($student);
        $record = TuitionRecord::create([
            'student_id' => $student->id,
            'billing_account_id' => $account->id,
            'school_year' => '2026-2027',
            'term' => 'Annual',
            'description' => 'Grade 7 payable',
            'tuition_amount' => 20000,
            'misc_amount' => 0,
            'other_amount' => 0,
            'total_amount' => 20000,
            'status' => PaymentStatus::Pending,
        ]);
        app(BalanceService::class)->recalculate($account);

        $payment = app(PaymentService::class)->submit([
            'student_id' => $student->id,
            'tuition_record_id' => $record->id,
            'amount' => 20000,
            'method' => 'online',
        ], '2', 'student');

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->receipt_number);
        $this->assertSame(PaymentStatus::Pending, $record->fresh()->status);
        $this->assertSame('20000.00', $account->balance->fresh()->current_balance);

        $confirmed = app(PaymentService::class)->confirm($payment, '2');

        $this->assertSame(PaymentStatus::Paid, $confirmed->status);
        $this->assertNotNull($confirmed->receipt_number);
        $this->assertSame(PaymentStatus::Paid, $record->fresh()->status);
        $this->assertSame('0.00', $account->balance->fresh()->current_balance);
    }

    public function test_student_api_payment_completes_without_cashier_confirmation(): void
    {
        $student = Student::create([
            'portal_user_id' => '2',
            'student_id' => 'DEORIS-2',
            'name' => 'Student',
            'program' => 'Grade 7',
            'year_level' => '7',
            'email' => 'student@example.com',
            'status' => 'active',
        ]);
        $account = app(BillingAccountService::class)->ensureForStudent($student);
        $record = TuitionRecord::create([
            'student_id' => $student->id,
            'billing_account_id' => $account->id,
            'school_year' => '2026-2027',
            'term' => 'Annual',
            'description' => 'Grade 7 payable',
            'tuition_amount' => 20000,
            'misc_amount' => 0,
            'other_amount' => 0,
            'total_amount' => 20000,
            'status' => PaymentStatus::Pending,
        ]);
        app(BalanceService::class)->recalculate($account);

        $response = $this
            ->withSession([
                'sso_id' => '2',
                'sso_name' => 'Student',
                'sso_email' => 'student@example.com',
                'sso_role' => 'student',
            ])
            ->postJson('/api/v1/payments', [
                'student_id' => $student->id,
                'tuition_record_id' => $record->id,
                'amount' => 20000,
                'method' => 'online',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'paid');
        $response->assertJsonPath('data.amount', 20000);
        $this->assertSame(PaymentStatus::Paid, $record->fresh()->status);
        $this->assertSame('0.00', $account->balance->fresh()->current_balance);
        $this->assertDatabaseHas('official_receipts', [
            'student_id' => $student->id,
            'amount' => 20000,
        ]);
    }

    public function test_cashier_api_cannot_create_student_payments(): void
    {
        $student = Student::create([
            'portal_user_id' => '2',
            'student_id' => 'DEORIS-2',
            'name' => 'Student',
            'program' => 'Grade 7',
            'year_level' => '7',
            'email' => 'student@example.com',
            'status' => 'active',
        ]);

        $response = $this
            ->withSession([
                'sso_id' => '7',
                'sso_name' => 'Cashier',
                'sso_email' => 'cashier@example.com',
                'sso_role' => 'cashier',
            ])
            ->postJson('/api/v1/payments', [
                'student_id' => $student->id,
                'amount' => 1500,
                'method' => 'cash',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_paid_payable_does_not_show_on_student_payment_form(): void
    {
        $student = Student::create([
            'portal_user_id' => '2',
            'student_id' => 'DEORIS-2',
            'name' => 'Student',
            'program' => 'Grade 7',
            'year_level' => '7',
            'email' => 'student@example.com',
            'status' => 'active',
        ]);
        $account = app(BillingAccountService::class)->ensureForStudent($student);
        TuitionRecord::create([
            'student_id' => $student->id,
            'billing_account_id' => $account->id,
            'school_year' => '2026-2027',
            'term' => 'Annual',
            'description' => 'Already paid payable',
            'tuition_amount' => 20000,
            'misc_amount' => 0,
            'other_amount' => 0,
            'total_amount' => 20000,
            'status' => PaymentStatus::Paid,
        ]);

        $response = $this
            ->withSession([
                'sso_id' => '2',
                'sso_name' => 'Student',
                'sso_email' => 'student@example.com',
                'sso_role' => 'student',
            ])
            ->get('/student');

        $response->assertOk();
        $response->assertDontSee('Already paid payable');
    }

    public function test_student_can_pay_specific_partial_amount(): void
    {
        $student = Student::create([
            'portal_user_id' => '2',
            'student_id' => 'DEORIS-2',
            'name' => 'Student',
            'program' => 'Grade 7',
            'year_level' => '7',
            'email' => 'student@example.com',
            'status' => 'active',
        ]);
        $account = app(BillingAccountService::class)->ensureForStudent($student);
        $record = TuitionRecord::create([
            'student_id' => $student->id,
            'billing_account_id' => $account->id,
            'school_year' => '2026-2027',
            'term' => 'Annual',
            'description' => 'Grade 7 payable',
            'tuition_amount' => 20000,
            'misc_amount' => 0,
            'other_amount' => 0,
            'total_amount' => 20000,
            'status' => PaymentStatus::Pending,
        ]);

        $response = $this
            ->withSession([
                'sso_id' => '2',
                'sso_name' => 'Student',
                'sso_email' => 'student@example.com',
                'sso_role' => 'student',
            ])
            ->postJson('/api/v1/payments', [
                'student_id' => $student->id,
                'tuition_record_id' => $record->id,
                'amount' => 1999.99,
                'method' => 'online',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'paid');
        $response->assertJsonPath('data.amount', 1999.99);
        $this->assertSame(PaymentStatus::PartiallyPaid, $record->fresh()->status);
        $this->assertSame('18000.01', $record->billingAccount->balance->fresh()->current_balance);
    }

    public function test_student_payment_subtracts_only_typed_amount_from_large_payable(): void
    {
        $student = Student::create([
            'portal_user_id' => '2',
            'student_id' => 'DEORIS-2',
            'name' => 'Student',
            'program' => 'Grade 7',
            'year_level' => '7',
            'email' => 'student@example.com',
            'status' => 'active',
        ]);
        $account = app(BillingAccountService::class)->ensureForStudent($student);
        $record = TuitionRecord::create([
            'student_id' => $student->id,
            'billing_account_id' => $account->id,
            'school_year' => '2026-2027',
            'term' => 'Annual',
            'description' => 'Grade 7 payable',
            'tuition_amount' => 30000,
            'misc_amount' => 0,
            'other_amount' => 0,
            'total_amount' => 30000,
            'status' => PaymentStatus::Pending,
        ]);
        app(BalanceService::class)->recalculate($account);

        $response = $this
            ->withSession([
                'sso_id' => '2',
                'sso_name' => 'Student',
                'sso_email' => 'student@example.com',
                'sso_role' => 'student',
            ])
            ->postJson('/api/v1/payments', [
                'student_id' => $student->id,
                'tuition_record_id' => $record->id,
                'amount' => 200,
                'method' => 'online',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'paid');
        $response->assertJsonPath('data.amount', 200);
        $this->assertSame(PaymentStatus::PartiallyPaid, $record->fresh()->status);
        $this->assertSame('29800.00', $account->balance->fresh()->current_balance);
    }

    public function test_student_api_cannot_read_another_students_financial_records(): void
    {
        $student = Student::create([
            'portal_user_id' => '2',
            'student_id' => 'DEORIS-2',
            'name' => 'Student One',
            'program' => 'Grade 7',
            'year_level' => '7',
            'email' => 'student.one@example.com',
            'status' => 'active',
        ]);
        app(BillingAccountService::class)->ensureForStudent($student);

        $other = Student::create([
            'portal_user_id' => '3',
            'student_id' => 'DEORIS-3',
            'name' => 'Student Two',
            'program' => 'Grade 8',
            'year_level' => '8',
            'email' => 'student.two@example.com',
            'status' => 'active',
        ]);
        $otherAccount = app(BillingAccountService::class)->ensureForStudent($other);
        $otherRecord = TuitionRecord::create([
            'student_id' => $other->id,
            'billing_account_id' => $otherAccount->id,
            'school_year' => '2026-2027',
            'term' => 'Annual',
            'description' => 'Other student payable',
            'tuition_amount' => 10000,
            'misc_amount' => 0,
            'other_amount' => 0,
            'total_amount' => 10000,
            'status' => PaymentStatus::Pending,
        ]);
        app(BalanceService::class)->recalculate($otherAccount);
        $otherPayment = Payment::create([
            'student_id' => $other->id,
            'billing_account_id' => $otherAccount->id,
            'tuition_record_id' => $otherRecord->id,
            'amount' => 1000,
            'method' => 'online',
            'reference_number' => 'OTHER-REF-123',
            'status' => PaymentStatus::Paid,
            'submitted_by_portal_id' => '3',
        ]);
        $otherReceipt = OfficialReceipt::create([
            'payment_id' => $otherPayment->id,
            'student_id' => $other->id,
            'receipt_number' => 'OR-OTHER-123',
            'amount' => 1000,
            'issued_by_portal_id' => '3',
            'issued_at' => now(),
        ]);

        $studentSession = [
            'sso_id' => '2',
            'sso_name' => 'Student One',
            'sso_email' => 'student.one@example.com',
            'sso_role' => 'student',
        ];

        $this->withSession($studentSession)->getJson('/api/v1/payments/'.$otherPayment->id)->assertNotFound();
        $this->withSession($studentSession)->getJson('/api/v1/receipts/'.$otherReceipt->id)->assertNotFound();
        $this->withSession($studentSession)->getJson('/api/v1/balances/'.$otherAccount->balance->id)->assertNotFound();
        $this->withSession($studentSession)->getJson('/api/v1/billing-records/'.$otherRecord->id)->assertNotFound();

        $this->withSession($studentSession)
            ->getJson('/api/v1/search?q=OTHER&type=all')
            ->assertOk()
            ->assertJsonCount(0, 'results.payments')
            ->assertJsonCount(0, 'results.receipts')
            ->assertJsonCount(0, 'results.billing')
            ->assertJsonCount(0, 'results.students');
    }

    public function test_reversing_paid_payment_reopens_payable_balance(): void
    {
        $student = Student::create([
            'portal_user_id' => '2',
            'student_id' => 'DEORIS-2',
            'name' => 'Student',
            'program' => 'Grade 7',
            'year_level' => '7',
            'email' => 'student@example.com',
            'status' => 'active',
        ]);
        $account = app(BillingAccountService::class)->ensureForStudent($student);
        $record = TuitionRecord::create([
            'student_id' => $student->id,
            'billing_account_id' => $account->id,
            'school_year' => '2026-2027',
            'term' => 'Annual',
            'description' => 'Reversible payable',
            'tuition_amount' => 5000,
            'misc_amount' => 0,
            'other_amount' => 0,
            'total_amount' => 5000,
            'status' => PaymentStatus::Pending,
        ]);
        app(BalanceService::class)->recalculate($account);

        $payment = app(PaymentService::class)->submit([
            'student_id' => $student->id,
            'tuition_record_id' => $record->id,
            'amount' => 5000,
            'method' => 'online',
        ], '2', 'student');
        app(PaymentService::class)->confirm($payment, '2');

        $this->assertSame(PaymentStatus::Paid, $record->fresh()->status);
        $this->assertSame('0.00', $account->balance->fresh()->current_balance);

        app(PaymentService::class)->reverse($payment->fresh(), '7', 'Duplicate payment');

        $this->assertSame(PaymentStatus::Pending, $record->fresh()->status);
        $this->assertSame('5000.00', $account->balance->fresh()->current_balance);
    }
}
