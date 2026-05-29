<?php

namespace App\Jobs;

use App\Models\EventOutbox;
use App\Services\EventHubPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishOutboxEventJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $outboxId) {}

    public function handle(EventHubPublisher $publisher): void
    {
        $outbox = EventOutbox::find($this->outboxId);
        if ($outbox && $outbox->status === 'pending') {
            $publisher->deliver($outbox);
        }
    }
}
