<?php

namespace Tests\Unit\Services;

use App\Enums\RentalType;
use App\Models\Garage;
use App\Services\RentalCostService;
use Tests\TestCase;

/**
 * Unit test: tidak menyentuh database. Garage cukup dibuat dengan "new Garage", tidak disimpan.
 */
class RentalCostServiceTest extends TestCase
{
    private function garage(float $dailyPrice, ?float $electricityPrice = null): Garage
    {
        return new Garage([
            'daily_price' => $dailyPrice,
            'electricity_price_per_kwh' => $electricityPrice,
        ]);
    }

    public function test_faktor_bulanan_adalah_25(): void
    {
        $this->assertSame(25, RentalCostService::MONTHLY_PRICE_FACTOR);
    }

    public function test_biaya_sewa_harian_adalah_jumlah_hari_kali_harga_harian(): void
    {
        $this->assertSame(15000.0, RentalCostService::rentCost($this->garage(5000), RentalType::Harian, 3));
    }

    public function test_biaya_sewa_bulanan_adalah_25_kali_harga_harian_berapa_pun_jumlah_harinya(): void
    {
        $garage = $this->garage(30000);

        $this->assertSame(750000.0, RentalCostService::rentCost($garage, RentalType::Bulanan, 30));
        $this->assertSame(750000.0, RentalCostService::rentCost($garage, RentalType::Bulanan, 31));
    }

    public function test_biaya_perpanjangan_selalu_dihitung_harian(): void
    {
        $this->assertSame(60000.0, RentalCostService::extensionCost($this->garage(30000), 2));
    }

    public function test_kwh_yang_dibutuhkan_sampai_baterai_penuh(): void
    {
        $this->assertSame(36.0, RentalCostService::chargeNeededKwh(60, 40));
        $this->assertSame(60.0, RentalCostService::chargeNeededKwh(60, 0));
        $this->assertSame(0.0, RentalCostService::chargeNeededKwh(60, 100));
    }

    public function test_kwh_dibulatkan_dua_angka_di_belakang_koma(): void
    {
        // 45.5 x (100 - 33.3) / 100 = 30.3485 -> 30.35
        $this->assertSame(30.35, RentalCostService::chargeNeededKwh(45.5, 33.3));
    }

    public function test_biaya_isi_listrik_adalah_kwh_kali_harga_per_kwh_garasi(): void
    {
        $this->assertSame(90000.0, RentalCostService::chargingCost($this->garage(50000, 2500), 36));
        $this->assertSame(0.0, RentalCostService::chargingCost($this->garage(50000, 2500), 0));
    }
}
