<?php

namespace App\Services;

use App\Jobs\PublishOutboxEventJob;
use App\Models\EventOutbox;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EventHubPublisher
{
    public function publish(string $eventName, array $payload, ?string $correlationId = null): EventOutbox
    {
        $eventId = (string) Str::uuid();
        $timestamp = now()->timestamp;
        $nonce = Str::random(32);
        $correlationId = $correlationId ?? (string) Str::uuid();

        $envelope = [
            'event_id' => $eventId,
            'event_name' => $eventName,
            'source_service' => config('assesspay.event_hub.source_service'),
            'payload' => $payload,
            'timestamp' => $timestamp,
            'schema_version' => config('assesspay.event_hub.schema_version'),
            'correlation_id' => $correlationId,
            'nonce' => $nonce,
        ];

        $signature = $this->sign($envelope);

        $outbox = EventOutbox::create([
            'id' => $eventId,
            'event_name' => $eventName,
            'source_service' => $envelope['source_service'],
            'payload' => $payload,
            'schema_version' => $envelope['schema_version'],
            'correlation_id' => $correlationId,
            'signature' => $signature,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'status' => 'pending',
        ]);

        PublishOutboxEventJob::dispatch($outbox->id)->onQueue(config('assesspay.queues.events'));

        return $outbox;
    }

    public function sign(array $envelope): string
    {
        $secret = config('assesspay.event_hub.secret');
        $canonical = json_encode([
            'event_id' => $envelope['event_id'],
            'event_name' => $envelope['event_name'],
            'source_service' => $envelope['source_service'],
            'payload' => $envelope['payload'],
            'timestamp' => $envelope['timestamp'],
            'schema_version' => $envelope['schema_version'],
            'correlation_id' => $envelope['correlation_id'],
            'nonce' => $envelope['nonce'],
        ], JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $canonical, $secret);
    }

    public function deliver(EventOutbox $outbox): bool
    {
        $url = config('assesspay.event_hub.url');
        if (! $url || ! config('assesspay.event_hub.secret')) {
            $outbox->update(['status' => 'published', 'published_at' => now()]);

            return true;
        }

        $body = [
            'event_id' => $outbox->id,
            'event_name' => $outbox->event_name,
            'source_service' => $outbox->source_service,
            'payload' => $outbox->payload,
            'timestamp' => $outbox->timestamp,
            'schema_version' => $outbox->schema_version,
            'correlation_id' => $outbox->correlation_id,
            'nonce' => $outbox->nonce,
            'signature' => $outbox->signature,
        ];

        $response = Http::timeout(10)
            ->withHeaders(['X-Event-Source' => config('assesspay.service_key')])
            ->post($url, $body);

        if ($response->successful()) {
            $outbox->update(['status' => 'published', 'published_at' => now()]);

            return true;
        }

        $outbox->update([
            'status' => 'failed',
            'attempts' => $outbox->attempts + 1,
            'last_error' => $response->body(),
        ]);

        return false;
    }
}
