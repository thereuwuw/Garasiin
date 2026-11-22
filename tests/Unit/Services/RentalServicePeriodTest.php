<?php

namespace Tests\Unit\Services;

use App\Enums\RentalType;
use App\Exceptions\GeneralException;
use App\Services\RentalService;
use Tests\TestCase;

/**
 * Unit test RentalService::period(). Tidak pakai database.
 * "Hari ini" dikunci ke 10 November 2026 (November = 30 hari) supaya hasilnya selalu sama.
 */
class RentalServicePeriodTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-10 09:00:00');
    }

    public function test_harian_tanpa_tanggal_mulai_dimulai_hari_ini(): void
    {
        $period = RentalService::period(RentalType::Harian, 3);

        $this->assertSame('2026-11-10', $period['start']->toDateString());
        $this->assertSame('2026-11-12', $period['end']->toDateString()); // 10 + 3 - 1
        $this->assertSame(3, $period['days']);
    }

    public function test_harian_satu_hari_selesai_di_hari_yang_sama(): void
    {
        $period = RentalService::period(RentalType::Harian, 1);

        $this->assertSame('2026-11-10', $period['end']->toDateString());
    }

    public function test_harian_dengan_tanggal_mulai_yang_dipilih(): void
    {
        $period = RentalService::period(RentalType::Harian, 5, '2026-11-15');

        $this->assertSame('2026-11-15', $period['start']->toDateString());
        $this->assertSame('2026-11-19', $period['end']->toDateString());
    }

    public function test_jumlah_hari_berupa_string_dari_form_tetap_diterima(): void
    {
        $this->assertSame(3, RentalService::period(RentalType::Harian, '3')['days']);
    }

    public function test_harian_tanggal_selesai_lewat_pergantian_bulan(): void
    {
        // Mulai 29 November, 3 hari: 29, 30 Nov, 1 Des.
        $period = RentalService::period(RentalType::Harian, 3, '2026-11-29');

        $this->assertSame('2026-11-29', $period['start']->toDateString());
        $this->assertSame('2026-12-01', $period['end']->toDateString());
        $this->assertSame(3, $period['days']);
    }

    public function test_harian_tanggal_selesai_lewat_pergantian_tahun(): void
    {
        // Mulai 30 Desember, 4 hari: 30, 31 Des, 1, 2 Jan.
        $period = RentalService::period(RentalType::Harian, 4, '2026-12-30');

        $this->assertSame('2027-01-02', $period['end']->toDateString());
    }

    public function test_harian_batas_maksimal_di_bulan_31_hari(): void
    {
        // Desember 31 hari: 30 diterima, 31 ditolak.
        $this->assertSame('2027-01-19', RentalService::period(RentalType::Harian, 30, '2026-12-21')['end']->toDateString());

        $this->expectExceptionMessage('Jumlah hari harus antara 1 dan 30 untuk bulan ini.');

        RentalService::period(RentalType::Harian, 31, '2026-12-01');
    }

    public function test_bulanan_sebanyak_jumlah_hari_bulan_mulai(): void
    {
        $period = RentalService::period(RentalType::Bulanan);

        $this->assertSame(30, $period['days']);
        $this->assertSame('2026-12-09', $period['end']->toDateString());
    }

    public function test_bulanan_mengabaikan_input_jumlah_hari(): void
    {
        $this->assertSame(30, RentalService::period(RentalType::Bulanan, 5)['days']);
    }

    public function test_bulanan_mulai_di_bulan_31_hari(): void
    {
        $period = RentalService::period(RentalType::Bulanan, null, '2026-12-20');

        $this->assertSame(31, $period['days']);
        $this->assertSame('2027-01-19', $period['end']->toDateString());
    }

    public function test_harian_maksimal_jumlah_hari_bulan_dikurangi_satu(): void
    {
        $this->assertSame(29, RentalService::period(RentalType::Harian, 29)['days']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Jumlah hari harus antara 1 dan 29 untuk bulan ini.');

        RentalService::period(RentalType::Harian, 30);
    }

    public function test_batas_maksimal_mengikuti_bulan_tanggal_mulai(): void
    {
        // Februari 2027 = 28 hari, jadi maksimal 27.
        $this->expectExceptionMessage('Jumlah hari harus antara 1 dan 27 untuk bulan ini.');

        RentalService::period(RentalType::Harian, 28, '2027-02-01');
    }

    public function test_harian_nol_hari_ditolak(): void
    {
        $this->expectException(GeneralException::class);

        RentalService::period(RentalType::Harian, 0);
    }

    public function test_harian_tanpa_jumlah_hari_ditolak(): void
    {
        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Jumlah hari wajib diisi untuk sewa harian.');

        RentalService::period(RentalType::Harian, null);
    }

    public function test_harian_jumlah_hari_bukan_angka_ditolak(): void
    {
        $this->expectExceptionMessage('Jumlah hari wajib diisi untuk sewa harian.');

        RentalService::period(RentalType::Harian, 'abc');
    }

    public function test_tanggal_mulai_sebelum_hari_ini_ditolak(): void
    {
        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Tanggal mulai tidak boleh sebelum hari ini.');

        RentalService::period(RentalType::Harian, 3, '2026-11-09');
    }
}
