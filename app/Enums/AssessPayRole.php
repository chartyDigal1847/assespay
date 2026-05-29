<?php

namespace App\Enums;

enum AssessPayRole: string
{
    case Cashier = 'cashier';
    case Student = 'student';
    case Admin = 'admin';

    public static function fromPortal(string $role): self
    {
        return match ($role) {
            'admin' => self::Admin,
            'hr', 'cashier' => self::Cashier,
            default => self::Student,
        };
    }
}
