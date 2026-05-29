<?php

namespace App\Events;

use App\Models\OfficialReceipt;
use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReceiptGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly OfficialReceipt $receipt,
        public readonly Payment $payment,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('assesspay.student.'.$this->payment->student_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'receipt.generated';
    }

    public function broadcastWith(): array
    {
        return [
            'receipt_id'     => $this->receipt->id,
            'receipt_number' => $this->receipt->receipt_number,
            'payment_id'     => $this->payment->id,
            'student_id'     => $this->payment->student_id,
            'amount'         => (float) $this->receipt->amount,
        ];
    }
}
