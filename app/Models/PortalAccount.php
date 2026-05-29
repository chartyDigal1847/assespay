<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Placeholder for Laravel auth config. AssessPay uses DEORIS SSO session keys only.
 */
class PortalAccount extends Authenticatable
{
    protected $table = 'sessions';
}
