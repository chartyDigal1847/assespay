<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('assesspay.student.'.$this->payment->student_id),
            new PrivateChannel('assesspay.cashier'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payment.confirmed';
    }

    public function broadcastWith(): array
    {
        return [
            'payment_id'     => $this->payment->id,
            'student_id'     => $this->payment->student_id,
            'amount'         => (float) $this->payment->amount,
            'receipt_number' => $this->payment->receipt_number,
            'status'         => $this->payment->status?->value ?? $this->payment->status,
        ];
    }
}
