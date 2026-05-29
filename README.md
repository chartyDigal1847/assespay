# AssessPay

DEORIS ecosystem microservice for tuition billing, payments, receipts, and financial reporting.

## Features

- SOA-isolated Laravel 12 service with owned MySQL database
- DEORIS Portal SSO (no standalone login)
- Role-based access: Cashier, Student, Admin
- REST API `/api/v1/*`
- Event Hub integration (HMAC-signed outbox)
- ClearCheck clearance via API only
- Redis queues, real-time UI updates
- MySQL views, procedures, triggers

## Quick start

```bash
cd asssesspay
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=AssessPaySeeder
php artisan serve
```

Dev SSO: `GET /sso/redirect?role=cashier&name=Cashier&email=cashier@test&id=1`

## Documentation

- [API](docs/API.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Database](docs/DATABASE.md)
- [Deployment](docs/DEPLOYMENT.md)
