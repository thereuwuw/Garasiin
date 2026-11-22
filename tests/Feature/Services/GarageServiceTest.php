<?php

namespace Tests\Feature\Services;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Exceptions\GeneralException;
use App\Models\Garage;
use App\Services\GarageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRentalData;
use Tests\TestCase;

class GarageServiceTest extends TestCase
{
    use CreatesRentalData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-10 09:00:00');
    }

    // ---------- createBatch() ----------

    public function test_membuat_garasi_berkelompok_dengan_kode_otomatis(): void
    {
        $garages = GarageService::createBatch([
            'type' => 'sepeda',
            'quantity' => 3,
            'daily_price' => 5000,
        ]);

        $this->assertSame(['S001', 'S002', 'S003'], $garages->pluck('code')->all());
        $this->assertSame([1, 2, 3], $garages->pluck('sequence')->all());
        $this->assertDatabaseCount('garages', 3);
        $this->assertDatabaseHas('garages', [
            'code' => 'S001',
            'type' => 'sepeda',
            'status' => 'available',
            'daily_price' => 5000,
            'electricity_price_per_kwh' => null,
        ]);
    }

    public function test_kode_melanjutkan_nomor_terakhir_tipe_yang_sama(): void
    {
        GarageService::createBatch(['type' => 'mobil', 'quantity' => 5, 'daily_price' => 30000]);

        $garages = GarageService::createBatch(['type' => 'mobil', 'quantity' => 3, 'daily_price' => 30000]);

        $this->assertSame(['C006', 'C007', 'C008'], $garages->pluck('code')->all());
    }

    public function test_nomor_tiap_tipe_garasi_berdiri_sendiri(): void
    {
        GarageService::createBatch(['type' => 'mobil', 'quantity' => 10, 'daily_price' => 30000]);

        $garages = GarageService::createBatch(['type' => 'sepeda', 'quantity' => 1, 'daily_price' => 5000]);

        $this->assertSame('S001', $garages->first()->code);
        $this->assertSame('C010', Garage::query()->where('type', 'mobil')->max('code'));
    }

    public function test_tipe_boleh_dikirim_sebagai_enum(): void
    {
        $garages = GarageService::createBatch([
            'type' => GarageType::MobilListrik,
            'quantity' => 1,
            'daily_price' => 50000,
            'electricity_price_per_kwh' => 2500,
        ]);

        $this->assertSame('E001', $garages->first()->code);
        $this->assertEquals(2500, $garages->first()->electricity_price_per_kwh);
    }

    public function test_garasi_mobil_listrik_wajib_punya_harga_listrik(): void
    {
        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Harga isi listrik per kWh wajib diisi untuk garasi mobil listrik.');

        GarageService::createBatch(['type' => 'mobil_listrik', 'quantity' => 1, 'daily_price' => 50000]);
    }

    public function test_harga_listrik_diabaikan_untuk_garasi_non_listrik(): void
    {
        GarageService::createBatch([
            'type' => 'mobil',
            'quantity' => 1,
            'daily_price' => 30000,
            'electricity_price_per_kwh' => 2500,
        ]);

        $this->assertNull(Garage::first()->electricity_price_per_kwh);
    }

    public function test_tipe_tidak_valid_ditolak(): void
    {
        $this->expectExceptionMessage('Tipe garasi tidak valid.');

        GarageService::createBatch(['type' => 'pesawat', 'quantity' => 1, 'daily_price' => 5000]);
    }

    public function test_jumlah_kurang_dari_satu_ditolak(): void
    {
        $this->expectExceptionMessage('Jumlah garasi minimal 1.');

        GarageService::createBatch(['type' => 'sepeda', 'quantity' => 0, 'daily_price' => 5000]);
    }

    public function test_harga_harian_nol_ditolak(): void
    {
        $this->expectExceptionMessage('Harga sewa harian harus lebih dari 0.');

        GarageService::createBatch(['type' => 'sepeda', 'quantity' => 1, 'daily_price' => 0]);
    }

    public function test_tidak_boleh_melebihi_kuota_999_per_tipe(): void
    {
        $this->makeGarage(GarageType::Sepeda, 998);

        try {
            GarageService::createBatch(['type' => 'sepeda', 'quantity' => 2, 'daily_price' => 5000]);
            $this->fail('Seharusnya melempar GeneralException.');
        } catch (GeneralException $e) {
            $this->assertSame('Sisa kuota Garasi Sepeda tinggal 1.', $e->getMessage());
        }

        $this->assertDatabaseCount('garages', 1); // tidak ada yang tersimpan

        $garages = GarageService::createBatch(['type' => 'sepeda', 'quantity' => 1, 'daily_price' => 5000]);
        $this->assertSame('S999', $garages->first()->code);
    }

    // ---------- availableFor() ----------

    public function test_garasi_yang_sudah_disewa_pada_periode_tidak_tersedia(): void
    {
        $tenant = $this->makeTenant();
        $vehicle = $this->makeVehicle($tenant);
        $booked = $this->makeGarage(GarageType::Sepeda, 1);
        $free = $this->makeGarage(GarageType::Sepeda, 2);
        $this->makeRental($tenant, $vehicle, $booked, '2026-11-10', '2026-11-12');

        $available = GarageService::availableFor(GarageType::Sepeda, today(), today()->addDays(2));

        $this->assertSame([$free->id], $available->pluck('id')->all());
    }

    public function test_tanggal_selesai_dihitung_inklusif_saat_cek_ketersediaan(): void
    {
        $tenant = $this->makeTenant();
        $vehicle = $this->makeVehicle($tenant);
        $garage = $this->makeGarage(GarageType::Sepeda, 1);
        $this->makeRental($tenant, $vehicle, $garage, '2026-11-05', '2026-11-10'); // selesai hari ini

        $this->assertCount(0, GarageService::availableFor(GarageType::Sepeda, today(), today()));
        $this->assertCount(1, GarageService::availableFor(GarageType::Sepeda, today()->addDay(), today()->addDays(3)));
    }

    public function test_sewa_yang_mulai_setelah_periode_tidak_menghalangi(): void
    {
        $tenant = $this->makeTenant();
        $vehicle = $this->makeVehicle($tenant);
        $garage = $this->makeGarage(GarageType::Sepeda, 1);
        $this->makeRental($tenant, $vehicle, $garage, '2026-11-20', '2026-11-22');

        $this->assertCount(1, GarageService::availableFor(GarageType::Sepeda, today(), today()->addDays(9))); // s.d. 19
        $this->assertCount(0, GarageService::availableFor(GarageType::Sepeda, today(), today()->addDays(10))); // s.d. 20
    }

    public function test_hanya_mengembalikan_tipe_yang_diminta_urut_sequence(): void
    {
        $this->makeGarage(GarageType::Sepeda, 2);
        $this->makeGarage(GarageType::Sepeda, 1);
        $this->makeGarage(GarageType::Mobil, 1, 30000);

        $available = GarageService::availableFor(GarageType::Sepeda, today(), today());

        $this->assertSame(['S001', 'S002'], $available->pluck('code')->all());
    }

    // ---------- syncStatuses() ----------

    public function test_sync_status_mengikuti_sewa_hari_ini(): void
    {
        $tenant = $this->makeTenant();
        $vehicle = $this->makeVehicle($tenant);
        $vehicle2 = $this->makeVehicle($tenant, VehicleType::SepedaMotor, 'L 2222 BB');

        $usedToday = $this->makeGarage(GarageType::Sepeda, 1);
        $endedYesterday = $this->makeGarage(GarageType::Sepeda, 2);
        $wronglyUsed = $this->makeGarage(GarageType::Sepeda, 3);
        $wronglyUsed->forceFill(['status' => GarageStatus::Used])->save();

        $this->makeRental($tenant, $vehicle, $usedToday, '2026-11-09', '2026-11-11');
        $this->makeRental($tenant, $vehicle2, $endedYesterday, '2026-11-05', '2026-11-09');
        $endedYesterday->forceFill(['status' => GarageStatus::Used])->save();

        GarageService::syncStatuses();

        $this->assertSame(GarageStatus::Used, $usedToday->fresh()->status);
        $this->assertSame(GarageStatus::Available, $endedYesterday->fresh()->status);
        $this->assertSame(GarageStatus::Available, $wronglyUsed->fresh()->status);
    }

    public function test_sync_status_menghitung_perpanjangan(): void
    {
        $tenant = $this->makeTenant();
        $vehicle = $this->makeVehicle($tenant);
        $garage = $this->makeGarage(GarageType::Sepeda, 1);
        $original = $this->makeRental($tenant, $vehicle, $garage, '2026-11-05', '2026-11-09');
        $this->makeExtension($original, '2026-11-10', '2026-11-12');

        GarageService::syncStatuses();

        $this->assertSame(GarageStatus::Used, $garage->fresh()->status);
    }
}
