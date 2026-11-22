<?php

namespace Tests\Feature\Http\Admin;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRentalData;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use CreatesRentalData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-10 09:00:00');

        $bambang = $this->makeTenant(['name' => 'Bambang']);
        $dewi = $this->makeTenant(['name' => 'Dewi']);
        $this->makeRental($bambang, $this->makeVehicle($bambang), $this->makeGarage(GarageType::Sepeda, 1, 5000), '2026-11-05', '2026-11-07');
        $this->makeRental($dewi, $this->makeVehicle($dewi, VehicleType::Mobil, 'L 2 B'), $this->makeGarage(GarageType::Mobil, 1, 30000), '2026-10-10', '2026-10-11');

        $this->actingAs($this->makeAdmin());
    }

    public function test_default_laporan_per_bulan_tahun_ini(): void
    {
        $this->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Per Bulan')
            ->assertSee('November')
            ->assertViewHas('type', 'bulanan')
            ->assertViewHas('year', 2026)
            ->assertViewHas('month', null)
            ->assertViewHas('years', [2027, 2026])
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 12)
            ->assertViewHas('totals', fn ($totals) => $totals['total_rentals'] === 2 && $totals['total_income'] === 75000.0);
    }

    public function test_laporan_bulanan_mengabaikan_bulan(): void
    {
        $this->get(route('admin.reports.index', ['type' => 'bulanan', 'month' => 3]))
            ->assertOk()
            ->assertViewHas('month', null);
    }

    public function test_laporan_per_hari_default_bulan_ini(): void
    {
        $this->get(route('admin.reports.index', ['type' => 'harian']))
            ->assertOk()
            ->assertSee('5 November 2026')
            ->assertViewHas('month', 11)
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 30)
            ->assertViewHas('totals', fn ($totals) => $totals['total_income'] === 15000.0);
    }

    public function test_laporan_per_hari_bulan_tertentu(): void
    {
        $this->get(route('admin.reports.index', ['type' => 'harian', 'year' => 2026, 'month' => 10]))
            ->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 31)
            ->assertViewHas('totals', fn ($totals) => $totals['total_income'] === 60000.0);
    }

    public function test_laporan_per_tipe_garasi(): void
    {
        $this->get(route('admin.reports.index', ['type' => 'tipe']))
            ->assertOk()
            ->assertSee('Garasi Sepeda')
            ->assertSee('Garasi Mobil Listrik')
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('total_income')->all() === [15000.0, 60000.0, 0.0]);

        $this->get(route('admin.reports.index', ['type' => 'tipe', 'month' => 11]))
            ->assertViewHas('totals', fn ($totals) => $totals['total_income'] === 15000.0);
    }

    public function test_laporan_per_penyewa(): void
    {
        $this->get(route('admin.reports.index', ['type' => 'penyewa']))
            ->assertOk()
            ->assertSee('Dewi')
            ->assertSee('Bambang')
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('period')->all() === ['Dewi', 'Bambang']);

        $this->get(route('admin.reports.index', ['type' => 'penyewa', 'month' => 11]))
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('period')->all() === ['Bambang']);
    }

    public function test_tahun_tanpa_transaksi_menampilkan_nol(): void
    {
        $this->get(route('admin.reports.index', ['year' => 2030]))
            ->assertOk()
            ->assertViewHas('totals', fn ($totals) => $totals['total_income'] === 0.0 && $totals['total_rentals'] === 0);
    }

    public function test_filter_tidak_valid_ditolak(): void
    {
        $this->get(route('admin.reports.index', [
            'type' => 'tahunan',
            'month' => 13,
            'year' => 1999,
        ]))->assertSessionHasErrors(['type', 'month', 'year']);
    }
}
