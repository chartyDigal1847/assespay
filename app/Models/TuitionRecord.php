<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TuitionRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'billing_account_id', 'student_id', 'school_year', 'term', 'description',
        'tuition_amount', 'misc_amount', 'other_amount', 'total_amount', 'status', 'due_date',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'status' => PaymentStatus::class,
            'tuition_amount' => 'decimal:2',
            'misc_amount' => 'decimal:2',
            'other_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
