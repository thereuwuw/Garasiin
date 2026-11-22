<?php

namespace App\Services;

use App\Enums\RentalType;
use App\Models\Garage;

class RentalCostService
{
    /**
     * Harga sewa bulanan selalu 25 x harga harian, berapa pun jumlah hari bulannya.
     */
    public const MONTHLY_PRICE_FACTOR = 25;

    /**
     * Biaya sewa baru. Harian: hari x harga harian. Bulanan: 25 x harga harian.
     */
    public static function rentCost(Garage $garage, RentalType $rentalType, int $days): float
    {
        $dailyPrice = (float) $garage->daily_price;

        return $rentalType === RentalType::Bulanan
            ? round($dailyPrice * self::MONTHLY_PRICE_FACTOR, 2)
            : round($dailyPrice * $days, 2);
    }

    /**
     * Biaya perpanjangan, selalu dihitung harian.
     */
    public static function extensionCost(Garage $garage, int $extraDays): float
    {
        return round((float) $garage->daily_price * $extraDays, 2);
    }

    /**
     * kWh yang dibutuhkan sampai penuh: kapasitas x (100 - persen saat ini) / 100.
     */
    public static function chargeNeededKwh(float $capacityKwh, float $currentPercent): float
    {
        return round($capacityKwh * (100 - $currentPercent) / 100, 2);
    }

    /**
     * Biaya isi listrik: kWh x harga listrik per kWh garasi.
     */
    public static function chargingCost(Garage $garage, float $neededKwh): float
    {
        return round($neededKwh * (float) $garage->electricity_price_per_kwh, 2);
    }
}
