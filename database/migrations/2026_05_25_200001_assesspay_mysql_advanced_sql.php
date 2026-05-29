<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW v_balance_summary AS
SELECT
    s.id AS student_id,
    s.student_id AS student_number,
    s.name AS student_name,
    ba.id AS billing_account_id,
    ba.account_number,
    b.total_assessed,
    b.total_paid,
    b.current_balance,
    b.last_recalculated_at
FROM balances b
INNER JOIN billing_accounts ba ON ba.id = b.billing_account_id
INNER JOIN students s ON s.id = b.student_id
WHERE ba.deleted_at IS NULL
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW v_tuition_analytics AS
SELECT
    tr.school_year,
    tr.term,
    tr.status,
    COUNT(*) AS record_count,
    SUM(tr.total_amount) AS total_billed,
    SUM(CASE WHEN tr.status = 'paid' THEN tr.total_amount ELSE 0 END) AS total_collected
FROM tuition_records tr
WHERE tr.deleted_at IS NULL
GROUP BY tr.school_year, tr.term, tr.status
SQL);

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_payment_trend_report');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE sp_payment_trend_report(IN months_back INT)
BEGIN
    SELECT
        DATE_FORMAT(COALESCE(p.confirmed_at, p.paid_at, p.created_at), '%Y-%m') AS period,
        COUNT(*) AS payment_count,
        SUM(p.amount) AS total_amount,
        SUM(CASE WHEN p.status = 'paid' THEN 1 ELSE 0 END) AS confirmed_count
    FROM payments p
    WHERE p.deleted_at IS NULL
      AND COALESCE(p.confirmed_at, p.paid_at, p.created_at) >= DATE_SUB(NOW(), INTERVAL months_back MONTH)
    GROUP BY period
    ORDER BY period DESC;
END
SQL);

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_cashier_transaction_summary');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE sp_cashier_transaction_summary(IN cashier_portal_id VARCHAR(64), IN from_date DATE, IN to_date DATE)
BEGIN
    SELECT
        p.confirmed_by_portal_id AS cashier_id,
        COUNT(*) AS transactions,
        SUM(p.amount) AS total_collected
    FROM payments p
    WHERE p.status = 'paid'
      AND p.deleted_at IS NULL
      AND p.confirmed_by_portal_id = cashier_portal_id
      AND DATE(p.confirmed_at) BETWEEN from_date AND to_date
    GROUP BY p.confirmed_by_portal_id;
END
SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS trg_payments_after_confirm');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_payments_after_confirm
AFTER UPDATE ON payments
FOR EACH ROW
BEGIN
    IF NEW.status = 'paid' AND (OLD.status IS NULL OR OLD.status <> 'paid') AND NEW.billing_account_id IS NOT NULL THEN
        UPDATE balances
        SET
            total_paid = total_paid + NEW.amount,
            current_balance = GREATEST(0, total_assessed - (total_paid + NEW.amount)),
            last_recalculated_at = NOW()
        WHERE billing_account_id = NEW.billing_account_id;
    END IF;
END
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_payments_after_confirm');
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_cashier_transaction_summary');
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_payment_trend_report');
        DB::unprepared('DROP VIEW IF EXISTS v_balance_summary');
        DB::unprepared('DROP VIEW IF EXISTS v_tuition_analytics');
    }
};
