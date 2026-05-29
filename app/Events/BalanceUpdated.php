<?php

namespace App\Events;

use App\Models\Balance;
use App\Models\BillingAccount;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BalanceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly BillingAccount $billingAccount,
        public readonly Balance $balance,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('assesspay.student.'.$this->billingAccount->student_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'balance.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'billing_account_id' => $this->billingAccount->id,
            'student_id'         => $this->billingAccount->student_id,
            'current_balance'    => (float) $this->balance->current_balance,
            'total_paid'         => (float) $this->balance->total_paid,
            'total_assessed'     => (float) $this->balance->total_assessed,
        ];
    }
}
