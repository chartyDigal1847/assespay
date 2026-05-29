<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'portal_user_id', 'student_id', 'name', 'program', 'year_level', 'email', 'status',
    ];

    public function billingAccount(): HasOne
    {
        return $this->hasOne(BillingAccount::class)->latestOfMany();
    }

    public function billingAccounts(): HasMany
    {
        return $this->hasMany(BillingAccount::class);
    }

    public function balance(): HasOne
    {
        return $this->hasOne(Balance::class)->latestOfMany();
    }

    public function balances(): HasMany
    {
        return $this->hasMany(Balance::class);
    }

    public function tuitionRecords(): HasMany
    {
        return $this->hasMany(TuitionRecord::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function officialReceipts(): HasMany
    {
        return $this->hasMany(OfficialReceipt::class);
    }

    public static function findByPortalOrEmail(?string $portalUserId, ?string $email): ?self
    {
        if ($portalUserId) {
            $found = static::where('portal_user_id', $portalUserId)->first();
            if ($found) {
                return $found;
            }
        }

        if ($email) {
            return static::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
        }

        return null;
    }
}
