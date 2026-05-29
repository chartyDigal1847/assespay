<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventOutbox extends Model
{
    protected $table = 'event_outbox';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'event_name', 'source_service', 'payload', 'schema_version',
        'correlation_id', 'signature', 'nonce', 'timestamp', 'status',
        'attempts', 'published_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'timestamp' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
