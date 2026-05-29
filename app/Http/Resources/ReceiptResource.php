<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'student_id' => $this->student_id,
            'receipt_number' => $this->receipt_number,
            'amount' => (float) $this->amount,
            'issued_by_portal_id' => $this->issued_by_portal_id,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }
}
