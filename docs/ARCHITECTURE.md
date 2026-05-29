# AssessPay SOA Architecture

```mermaid
flowchart LR
    Portal[DEORIS Portal SSO]
    AP[AssessPay Service]
    DB[(MySQL assesspaydb)]
    Redis[(Redis)]
    Hub[Event Hub]
    CC[ClearCheck API]

    Portal -->|token exchange| AP
    AP --> DB
    AP --> Redis
    AP -->|HMAC signed events| Hub
    AP -->|clearance API| CC
```

## Principles

- **Own database** — no cross-service joins
- **REST + events** — external communication only
- **Portal SSO** — no standalone auth
- **Role enforcement** — Cashier confirms; Admin read-only; Student pay/view only

## Service identity

| Key | Env |
|-----|-----|
| service_key | `ASSESSPAY_SERVICE_KEY` |
| event_secret | `ASSESSPAY_EVENT_SECRET` |
| queues | payments, billing, notifications, events |

## Event catalog

- `TuitionPaid`, `BalanceUpdated`, `ReceiptGenerated`, `PaymentConfirmed`, `FinancialRecordUpdated`

Each outbox row includes: event_id, event_name, source_service, payload, timestamp, schema_version, correlation_id, HMAC signature, nonce.
