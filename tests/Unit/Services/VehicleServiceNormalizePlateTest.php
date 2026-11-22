<?php

namespace Tests\Unit\Services;

use App\Services\VehicleService;
use PHPUnit\Framework\TestCase;

class VehicleServiceNormalizePlateTest extends TestCase
{
    public function test_plat_dirapikan_menjadi_huruf_besar_dengan_satu_spasi(): void
    {
        $this->assertSame('L 1234 AB', VehicleService::normalizePlate('  l 1234   ab '));
    }

    public function test_plat_yang_sudah_rapi_tidak_berubah(): void
    {
        $this->assertSame('B 1 XYZ', VehicleService::normalizePlate('B 1 XYZ'));
    }

    public function test_plat_kosong_menjadi_null(): void
    {
        $this->assertNull(VehicleService::normalizePlate(''));
        $this->assertNull(VehicleService::normalizePlate('   '));
    }

    public function test_input_bukan_string_menjadi_null(): void
    {
        $this->assertNull(VehicleService::normalizePlate(null));
        $this->assertNull(VehicleService::normalizePlate(1234));
    }
}
