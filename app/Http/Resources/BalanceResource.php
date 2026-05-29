<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'billing_account_id' => $this->billing_account_id,
            'student_id' => $this->student_id,
            'total_assessed' => (float) $this->total_assessed,
            'total_paid' => (float) $this->total_paid,
            'current_balance' => (float) $this->current_balance,
            'last_recalculated_at' => $this->last_recalculated_at?->toIso8601String(),
            'student' => new StudentResource($this->whenLoaded('student')),
        ];
    }
}
