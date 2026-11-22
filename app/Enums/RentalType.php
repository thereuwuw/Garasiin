<?php

namespace App\Enums;

enum RentalType: string
{
    case Harian = 'harian';
    case Bulanan = 'bulanan';

    public function label(): string
    {
        return match ($this) {
            self::Harian => 'Harian',
            self::Bulanan => 'Bulanan',
        };
    }
}
