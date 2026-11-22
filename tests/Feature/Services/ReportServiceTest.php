<?php

namespace Tests\Feature\Services;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRentalData;
use Tests\TestCase;

/**
 * Laporan pendapatan: per bulan, per hari, per tipe garasi, per penyewa.
 * Transaksi dihitung pada bulan/tanggal MULAI sewanya.
 */
class ReportServiceTest extends TestCase
{
    use CreatesRentalData, RefreshDatabase;

    private User $bambang;

    private User $dewi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-10 09:00:00');

        $this->bambang = $this->makeTenant(['name' => 'Bambang', 'email' => 'bambang@gmail.com']);
        $this->dewi = $this->makeTenant(['name' => 'Dewi', 'email' => 'dewi@gmail.com']);
        $this->makeTenant(['name' => 'Citra']); // penyewa tanpa transaksi

        $bike = $this->makeGarage(GarageType::Sepeda, 1, 5000);
        $ev = $this->makeGarage(GarageType::MobilListrik, 1, 50000, 2500);

        $motor = $this->makeVehicle($this->bambang, VehicleType::SepedaMotor, 'L 1 A');
        $ioniq = $this->makeVehicle($this->dewi, VehicleType::MobilListrik, 'L 2 B', 60);

        // 5 Nov 2026, Bambang, sepeda: 3 hari = 15.000
        $nov = $this->makeRental($this->bambang, $motor, $bike, '2026-11-05', '2026-11-07');
        // 8 Nov 2026, perpanjangan 2 hari = 10.000
        $this->makeExtension($nov, '2026-11-08', '2026-11-09');

        // 10 Okt 2026, Dewi, mobil listrik: 2 hari = 100.000 + listrik 90.000
        $oct = $this->makeRental($this->dewi, $ioniq, $ev, '2026-10-10', '2026-10-11');
        $oct->charging()->create([
            'current_battery_percent' => 40,
            'charge_needed_kwh' => 36,
            'charging_cost' => 90000,
            'payment_proof' => 'proofs/listrik.jpg',
        ]);

        // 31 Des 2025 (di luar tahun 2026): 1 hari = 5.000
        $this->makeRental($this->bambang, $motor, $bike, '2025-12-31', '2025-12-31');
    }

    private function emptyRow(int|string $period): array
    {
        return [
            'period' => $period,
            'total_rentals' => 0,
            'total_extensions' => 0,
            'rent_income' => 0.0,
            'charging_income' => 0.0,
            'total_income' => 0.0,
        ];
    }

    // ---------- monthly() ----------

    public function test_per_bulan_selalu_12_baris(): void
    {
        $rows = ReportService::monthly(2026);

        $this->assertCount(12, $rows);
        $this->assertSame(range(1, 12), $rows->pluck('period')->all());
        $this->assertSame($this->emptyRow(1), $rows[0]); // Januari kosong
    }

    public function test_per_bulan_menghitung_sewa_perpanjangan_dan_pendapatan(): void
    {
        $rows = ReportService::monthly(2026)->keyBy('period');

        $this->assertSame([
            'period' => 10,
            'total_rentals' => 1,
            'total_extensions' => 0,
            'rent_income' => 100000.0,
            'charging_income' => 90000.0,
            'total_income' => 190000.0,
        ], $rows[10]);

        $this->assertSame([
            'period' => 11,
            'total_rentals' => 1,
            'total_extensions' => 1,
            'rent_income' => 25000.0,
            'charging_income' => 0.0,
            'total_income' => 25000.0,
        ], $rows[11]);
    }

    public function test_per_bulan_tidak_memasukkan_transaksi_tahun_lain(): void
    {
        $this->assertSame(215000.0, ReportService::monthly(2026)->sum('total_income'));
        $this->assertSame(5000.0, ReportService::monthly(2025)->sum('total_income'));
        $this->assertSame(5000.0, ReportService::monthly(2025)->keyBy('period')[12]['total_income']);
    }

    // ---------- daily() ----------

    public function test_per_hari_sebanyak_jumlah_hari_bulan_itu(): void
    {
        $this->assertCount(30, ReportService::daily(2026, 11));
        $this->assertCount(31, ReportService::daily(2026, 12));
        $this->assertCount(28, ReportService::daily(2027, 2));
    }

    public function test_per_hari_menghitung_transaksi_di_tanggal_mulai(): void
    {
        $rows = ReportService::daily(2026, 11)->keyBy('period');

        $this->assertSame(1, $rows[5]['total_rentals']);
        $this->assertSame(15000.0, $rows[5]['total_income']);
        $this->assertSame(1, $rows[8]['total_extensions']);
        $this->assertSame(10000.0, $rows[8]['total_income']);
        $this->assertSame($this->emptyRow(6), $rows[6]);
    }

    public function test_per_hari_tidak_memasukkan_transaksi_bulan_lain(): void
    {
        $this->assertSame(25000.0, ReportService::daily(2026, 11)->sum('total_income'));
        $this->assertSame(190000.0, ReportService::daily(2026, 10)->sum('total_income'));
        $this->assertSame(0.0, ReportService::daily(2026, 12)->sum('total_income'));
    }

    // ---------- byGarageType() ----------

    public function test_per_tipe_garasi_selalu_3_baris(): void
    {
        $rows = ReportService::byGarageType(2026);

        $this->assertSame(['sepeda', 'mobil', 'mobil_listrik'], $rows->pluck('period')->all());
        $this->assertSame(25000.0, $rows[0]['total_income']);
        $this->assertSame($this->emptyRow('mobil'), $rows[1]);
        $this->assertSame(190000.0, $rows[2]['total_income']);
        $this->assertSame(90000.0, $rows[2]['charging_income']);
    }

    public function test_per_tipe_garasi_bisa_dibatasi_satu_bulan(): void
    {
        $rows = ReportService::byGarageType(2026, 10)->keyBy('period');

        $this->assertSame(0.0, $rows['sepeda']['total_income']);
        $this->assertSame(190000.0, $rows['mobil_listrik']['total_income']);
    }

    // ---------- byTenant() ----------

    public function test_per_penyewa_urut_pendapatan_terbesar(): void
    {
        $rows = ReportService::byTenant(2026);

        $this->assertSame(['Dewi', 'Bambang'], $rows->pluck('period')->all());
        $this->assertSame('dewi@gmail.com', $rows[0]['email']);
        $this->assertSame(190000.0, $rows[0]['total_income']);
        $this->assertSame(25000.0, $rows[1]['total_income']);
        $this->assertSame(1, $rows[1]['total_extensions']);
    }

    public function test_per_penyewa_hanya_yang_punya_transaksi_di_periode(): void
    {
        $rows = ReportService::byTenant(2026, 11);

        $this->assertSame(['Bambang'], $rows->pluck('period')->all());
        $this->assertSame(25000.0, $rows[0]['total_income']); // transaksi 2025 tidak ikut
    }

    // ---------- years() ----------

    public function test_pilihan_tahun_dari_tahun_depan_sampai_tahun_sewa_pertama(): void
    {
        $this->assertSame([2027, 2026, 2025], ReportService::years());
    }
}
