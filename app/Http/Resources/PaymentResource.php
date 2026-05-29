<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'billing_account_id' => $this->billing_account_id,
            'tuition_record_id' => $this->tuition_record_id,
            'amount' => (float) $this->amount,
            'status' => $this->status?->value ?? $this->status,
            'method' => $this->method,
            'reference_number' => $this->reference_number,
            'receipt_number' => $this->receipt_number,
            'submitted_by_portal_id' => $this->submitted_by_portal_id,
            'confirmed_by_portal_id' => $this->confirmed_by_portal_id,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'correlation_id' => $this->correlation_id,
            'student' => new StudentResource($this->whenLoaded('student')),
            'official_receipt' => new ReceiptResource($this->whenLoaded('officialReceipt')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
