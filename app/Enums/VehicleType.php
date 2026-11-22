<?php

namespace App\Enums;

enum VehicleType: string
{
    case Sepeda = 'sepeda';
    case SepedaMotor = 'sepeda_motor';
    case Mobil = 'mobil';
    case MobilListrik = 'mobil_listrik';

    public function label(): string
    {
        return match ($this) {
            self::Sepeda => 'Sepeda',
            self::SepedaMotor => 'Sepeda Motor',
            self::Mobil => 'Mobil',
            self::MobilListrik => 'Mobil Listrik',
        };
    }

    /**
     * Tipe garasi yang boleh dipakai kendaraan ini.
     *
     * @return list<GarageType>
     */
    public function allowedGarageTypes(): array
    {
        return match ($this) {
            self::Sepeda, self::SepedaMotor => [GarageType::Sepeda],
            self::Mobil => [GarageType::Mobil],
            self::MobilListrik => [GarageType::Mobil, GarageType::MobilListrik],
        };
    }

    public function needsPlate(): bool
    {
        return $this !== self::Sepeda;
    }
}
