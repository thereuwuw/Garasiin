<?php

namespace Tests\Feature\Services;

use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceYearsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tanpa_data_sewa_pilihan_tahun_adalah_tahun_depan_dan_tahun_ini(): void
    {
        $this->travelTo('2026-11-10');

        $this->assertSame([2027, 2026], ReportService::years());
    }
}
