# AssessPay REST API (v1)

Base URL: `{ASSESSPAY_SERVICE_URL}/api/v1`

Authentication: DEORIS Portal session cookie (SSO). All endpoints require an active portal session unless using service API key for machine-to-machine routes.

## Payments

| Method | Path | Role |
|--------|------|------|
| GET | `/payments` | All (students scoped) |
| GET | `/payments/{id}` | All |
| POST | `/payments` | Student, Cashier |
| PUT | `/payments/{id}` | Cashier |
| DELETE | `/payments/{id}` | Cashier |
| POST | `/payments/{id}/confirm` | **Cashier only** |
| POST | `/payments/{id}/reverse` | **Cashier only** |

## Balances

| Method | Path | Role |
|--------|------|------|
| GET | `/balances` | All |
| PUT | `/billing-accounts/{id}/balance` | **Cashier only** |
| POST | `/billing-accounts/{id}/recalculate` | Cashier |

## Receipts, Billing, Analytics, Search

- `GET /receipts`, `GET /billing-records`, `GET /financial-analytics` (admin/cashier)
- `GET /search?q=term&type=all`
- `GET /clearance/{studentNumber}` — ClearCheck integration (API only)

## Response format

JSON with Laravel API Resources. Errors: `{ "success": false, "error": "code", "message": "..." }`
