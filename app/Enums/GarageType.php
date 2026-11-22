<?php

namespace App\Enums;

enum GarageType: string
{
    case Sepeda = 'sepeda';
    case Mobil = 'mobil';
    case MobilListrik = 'mobil_listrik';

    public function label(): string
    {
        return match ($this) {
            self::Sepeda => 'Garasi Sepeda',
            self::Mobil => 'Garasi Mobil',
            self::MobilListrik => 'Garasi Mobil Listrik',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Sepeda => 'S',
            self::Mobil => 'C',
            self::MobilListrik => 'E',
        };
    }
}
