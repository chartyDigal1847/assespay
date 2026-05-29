# AssessPay Database Schema

## Core tables

| Table | Purpose |
|-------|---------|
| students | Student identity (portal_user_id, soft deletes) |
| billing_accounts | Financial account per student |
| tuition_records | Tuition/fee line items |
| balances | Aggregated balance per billing account |
| payments | Payment requests and confirmations |
| payment_methods | Cash, bank, online |
| official_receipts | Issued OR after cashier confirmation |
| financial_transactions | Ledger entries |
| payment_audit_logs | Payment state changes |
| activity_logs | General audit trail |
| event_outbox | Reliable event publishing |
| portal_user_sync | SSO user cache |

## MySQL advanced (production)

- **Views:** `v_balance_summary`, `v_tuition_analytics`
- **Procedures:** `sp_payment_trend_report`, `sp_cashier_transaction_summary`
- **Trigger:** `trg_payments_after_confirm` — auto balance update on confirm

See `database/sql/mysql_advanced.sql`.
