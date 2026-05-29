<?php

namespace App\Listeners;

use App\Events\TuitionPaid;
use Illuminate\Support\Facades\Redis;

class PublishTuitionPaidToRedis
{
    public function handle(TuitionPaid $event): void
    {
        $channel = config('assesspay.redis.channels.payments');
        Redis::publish($channel, json_encode([
            'event' => 'TuitionPaid',
            'payment_id' => $event->payment->id,
            'student_id' => $event->payment->student_id,
            'amount' => (float) $event->payment->amount,
            'timestamp' => now()->toIso8601String(),
        ]));
    }
}
