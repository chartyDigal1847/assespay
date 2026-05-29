<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id', 'account_number', 'currency', 'status',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function balance(): HasOne
    {
        return $this->hasOne(Balance::class);
    }

    public function tuitionRecords(): HasMany
    {
        return $this->hasMany(TuitionRecord::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
