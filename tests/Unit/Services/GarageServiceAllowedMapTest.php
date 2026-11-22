<?php

namespace Tests\Unit\Services;

use App\Services\GarageService;
use PHPUnit\Framework\TestCase;

class GarageServiceAllowedMapTest extends TestCase
{
    public function test_peta_tipe_kendaraan_ke_tipe_garasi_dalam_bentuk_string(): void
    {
        $this->assertSame([
            'sepeda' => ['sepeda'],
            'sepeda_motor' => ['sepeda'],
            'mobil' => ['mobil'],
            'mobil_listrik' => ['mobil', 'mobil_listrik'],
        ], GarageService::allowedMap());
    }

    public function test_kuota_maksimal_per_tipe_adalah_999(): void
    {
        $this->assertSame(999, GarageService::MAX_PER_TYPE);
    }
}
