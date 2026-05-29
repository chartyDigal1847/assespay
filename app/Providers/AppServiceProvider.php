<?php

namespace App\Providers;

use App\Events\BalanceUpdated;
use App\Events\FinancialRecordUpdated;
use App\Events\PaymentConfirmed;
use App\Events\ReceiptGenerated;
use App\Events\TuitionPaid;
use App\Listeners\NotifyOnBalanceUpdated;
use App\Listeners\NotifyOnPaymentConfirmed;
use App\Listeners\NotifyOnReceiptGenerated;
use App\Listeners\PublishTuitionPaidToRedis;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // TuitionPaid → publish to Redis pub/sub channel
        Event::listen(TuitionPaid::class, PublishTuitionPaidToRedis::class);

        // PaymentConfirmed → notify student
        Event::listen(PaymentConfirmed::class, NotifyOnPaymentConfirmed::class);

        // ReceiptGenerated → notify student
        Event::listen(ReceiptGenerated::class, NotifyOnReceiptGenerated::class);

        // BalanceUpdated → notify student
        Event::listen(BalanceUpdated::class, NotifyOnBalanceUpdated::class);

        // Route model bindings
        \Illuminate\Support\Facades\Route::bind(
            'receipt',
            fn ($value) => \App\Models\OfficialReceipt::findOrFail($value)
        );
        \Illuminate\Support\Facades\Route::bind(
            'billingRecord',
            fn ($value) => \App\Models\TuitionRecord::findOrFail($value)
        );
    }

    private function repinEnvFromFile(): void
    {
        $envFile = base_path('.env');
        if (! is_readable($envFile)) { return; }
        $pin = ['APP_KEY', 'APP_ENV', 'SESSION_DRIVER', 'SESSION_COOKIE',
                'SESSION_DOMAIN', 'SESSION_SECURE_COOKIE', 'SESSION_SAME_SITE',
                'BROADCAST_CONNECTION', 'DB_CONNECTION', 'DB_DATABASE'];
        $map = [
            'APP_KEY'               => 'app.key',
            'APP_ENV'               => 'app.env',
            'SESSION_DRIVER'        => 'session.driver',
            'SESSION_COOKIE'        => 'session.cookie',
            'SESSION_SAME_SITE'     => 'session.same_site',
            'SESSION_SECURE_COOKIE' => 'session.secure',
            'BROADCAST_CONNECTION'  => 'broadcasting.default',
            'DB_DATABASE'           => 'database.connections.mysql.database',
        ];
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line === '' || $line[0] === '#') { continue; }
            $eq = strpos($line, '=');
            if ($eq === false) { continue; }
            $key = trim(substr($line, 0, $eq));
            if (! in_array($key, $pin, true)) { continue; }
            $val = trim(substr($line, $eq + 1));
            if (strlen($val) >= 2 && $val[0] === '"' && $val[-1] === '"') { $val = substr($val, 1, -1); }
            elseif (strlen($val) >= 2 && $val[0] === "'" && $val[-1] === "'") { $val = substr($val, 1, -1); }
            $_SERVER[$key] = $val;
            if (isset($map[$key])) { config([$map[$key] => $val]); }
        }
    }
}
