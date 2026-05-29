<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Balance extends Model
{
    protected $fillable = [
        'billing_account_id', 'student_id', 'total_assessed', 'total_paid',
        'current_balance', 'last_recalculated_at',
    ];

    protected function casts(): array
    {
        return [
            'total_assessed' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'last_recalculated_at' => 'datetime',
        ];
    }

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
