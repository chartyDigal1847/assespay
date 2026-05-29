<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogger
{
    public function log(
        string $event,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $properties = null,
        ?Request $request = null,
    ): ActivityLog {
        return ActivityLog::create([
            'event' => $event,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'causer_portal_id' => session('assesspay_portal_id'),
            'causer_role' => session('assesspay_role'),
            'properties' => $properties,
            'ip_address' => $request?->ip(),
        ]);
    }
}
