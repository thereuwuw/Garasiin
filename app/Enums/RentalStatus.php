<?php

namespace App\Enums;

enum RentalStatus: string
{
    case New = 'new';
    case Extend = 'extend';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Sewa Baru',
            self::Extend => 'Perpanjangan',
        };
    }
}
