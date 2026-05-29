# AssessPay Deployment Guide

## Requirements

- PHP 8.2+, Laravel 12
- MySQL 8+ (for views/triggers/procedures)
- Redis (queues, cache, pub/sub, Reverb)
- Supervisor (queue workers)
- HTTPS subdomain e.g. `assesspay.deoris.test`

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=AssessPaySeeder
npm install && npm run build
```

## Environment

```env
DB_CONNECTION=mysql
DB_DATABASE=assespaydb
QUEUE_CONNECTION=redis
CACHE_STORE=redis
BROADCAST_CONNECTION=reverb
ASSESSPAY_SERVICE_URL=https://assesspay.deoris.test
ASSESSPAY_EVENT_SECRET=your-hmac-secret
PORTAL_BASE_URL=https://deoris.test
AUTH_SERVICE_URL=https://deoris.test
EVENT_HUB_URL=https://events.deoris.test/api/v1/publish
CLEARCHECK_API_URL=https://clearcheck.deoris.test/api/v1
```

## Supervisor (queues)

```ini
[program:assesspay-payments]
command=php /path/artisan queue:work redis --queue=payments --sleep=3
numprocs=2

[program:assesspay-events]
command=php /path/artisan queue:work redis --queue=events,billing,notifications --sleep=3
```

## Scheduler

```cron
* * * * * php /path/artisan schedule:run
```

Add `PublishPendingEvents` command if batch retry needed.
