<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly string $portalUserId,
        public readonly string $type,
        public readonly string $title,
        public readonly string $message,
        public readonly array  $payload = [],
    ) {}

    public function handle(): void
    {
        Notification::create([
            'portal_user_id' => $this->portalUserId,
            'type'           => $this->type,
            'title'          => $this->title,
            'message'        => $this->message,
            'payload'        => $this->payload,
            'read_at'        => null,
        ]);

        Log::info('[AssessPay][Notification] Sent', [
            'portal_user_id' => $this->portalUserId,
            'type'           => $this->type,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[AssessPay][Notification] Job failed', [
            'portal_user_id' => $this->portalUserId,
            'type'           => $this->type,
            'error'          => $e->getMessage(),
        ]);
    }
}
