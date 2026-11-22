<?php

namespace Tests\Feature\Services;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\RentalStatus;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Exceptions\GeneralException;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Services\RentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRentalData;
use Tests\TestCase;

/**
 * "Hari ini" dikunci ke 10 November 2026 (November = 30 hari).
 */
class RentalServiceTest extends TestCase
{
    use CreatesRentalData, RefreshDatabase;

    private User $tenant;

    private Garage $bikeGarage;   // S001, 5.000/hari

    private Garage $carGarage;    // C001, 30.000/hari

    private Garage $evGarage;     // E001, 50.000/hari, 2.500/kWh

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-10 09:00:00');

        $this->tenant = $this->makeTenant();
        $this->bikeGarage = $this->makeGarage(GarageType::Sepeda, 1, 5000);
        $this->carGarage = $this->makeGarage(GarageType::Mobil, 1, 30000);
        $this->evGarage = $this->makeGarage(GarageType::MobilListrik, 1, 50000, 2500);
    }

    private function motorData(array $overrides = []): array
    {
        return array_merge([
            'vehicle' => [
                'type' => 'sepeda_motor',
                'plate_number' => 'l 5678 cd',
                'brand' => 'Honda',
                'model' => 'Vario 160',
            ],
            'garage_id' => $this->bikeGarage->id,
            'rental_type' => 'harian',
            'days' => 3,
            'payment_proof_path' => 'proofs/bayar.jpg',
        ], $overrides);
    }

    private function evData(array $overrides = []): array
    {
        return array_merge([
            'vehicle' => [
                'type' => 'mobil_listrik',
                'plate_number' => 'L 1234 AB',
                'brand' => 'Hyundai',
                'model' => 'Ioniq 5',
                'max_battery_capacity_kwh' => 60,
            ],
            'garage_id' => $this->evGarage->id,
            'rental_type' => 'harian',
            'days' => 2,
            'current_battery_percent' => 40,
            'payment_proof_path' => 'proofs/bayar.jpg',
            'charging_payment_proof_path' => 'proofs/listrik.jpg',
        ], $overrides);
    }

    // ================= create() =================

    public function test_sewa_harian_baru_tersimpan_dan_garasi_menjadi_used(): void
    {
        $rental = RentalService::create($this->tenant, $this->motorData());

        $this->assertSame(RentalStatus::New, $rental->status);
        $this->assertNull($rental->parent_id);
        $this->assertSame(RentalType::Harian, $rental->rental_type);
        $this->assertSame(3, $rental->duration_days);
        $this->assertSame('2026-11-10', $rental->start_date->toDateString());
        $this->assertSame('2026-11-12', $rental->end_date->toDateString());
        $this->assertEquals(15000, $rental->rent_cost);
        $this->assertSame('proofs/bayar.jpg', $rental->payment_proof);
        $this->assertSame('L 5678 CD', $rental->vehicle->plate_number);
        $this->assertNull($rental->charging);
        $this->assertSame(GarageStatus::Used, $this->bikeGarage->fresh()->status);
    }

    public function test_sewa_bulanan_biayanya_25_kali_harga_harian(): void
    {
        $rental = RentalService::create($this->tenant, [
            'vehicle' => ['type' => 'mobil', 'plate_number' => 'B 1 AA', 'brand' => 'Toyota', 'model' => 'Avanza'],
            'garage_id' => $this->carGarage->id,
            'rental_type' => 'bulanan',
            'payment_proof_path' => 'proofs/bayar.jpg',
        ]);

        $this->assertSame(RentalType::Bulanan, $rental->rental_type);
        $this->assertSame(30, $rental->duration_days);
        $this->assertSame('2026-12-09', $rental->end_date->toDateString());
        $this->assertEquals(750000, $rental->rent_cost);
    }

    public function test_sewa_yang_mulai_besok_tidak_mengubah_status_garasi(): void
    {
        $rental = RentalService::create($this->tenant, $this->motorData(['start_date' => '2026-11-11']));

        $this->assertSame('2026-11-11', $rental->start_date->toDateString());
        $this->assertSame('2026-11-13', $rental->end_date->toDateString());
        $this->assertSame(GarageStatus::Available, $this->bikeGarage->fresh()->status);
    }

    public function test_mobil_listrik_di_garasi_listrik_wajib_isi_sampai_penuh(): void
    {
        $rental = RentalService::create($this->tenant, $this->evData());

        // 60 kWh x (100 - 40)% = 36 kWh x 2.500 = 90.000
        $this->assertNotNull($rental->charging);
        $this->assertEquals(40, $rental->charging->current_battery_percent);
        $this->assertEquals(36, $rental->charging->charge_needed_kwh);
        $this->assertEquals(90000, $rental->charging->charging_cost);
        $this->assertSame('proofs/listrik.jpg', $rental->charging->payment_proof);
        $this->assertEquals(100000, $rental->rent_cost); // 2 x 50.000
    }

    public function test_bukti_bayar_listrik_wajib_kalau_ada_biaya_listrik(): void
    {
        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Bukti pembayaran isi listrik wajib diupload.');

        RentalService::create($this->tenant, $this->evData(['charging_payment_proof_path' => null]));
    }

    public function test_baterai_100_persen_tidak_membuat_data_charging(): void
    {
        $rental = RentalService::create($this->tenant, $this->evData([
            'current_battery_percent' => 100,
            'charging_payment_proof_path' => null,
        ]));

        $this->assertNull($rental->charging);
        $this->assertDatabaseCount('chargings', 0);
    }

    public function test_mobil_listrik_di_garasi_mobil_biasa_tidak_isi_listrik(): void
    {
        $rental = RentalService::create($this->tenant, $this->evData([
            'garage_id' => $this->carGarage->id,
            'current_battery_percent' => null,
            'charging_payment_proof_path' => null,
        ]));

        $this->assertNull($rental->charging);
    }

    public function test_persentase_baterai_wajib_untuk_mobil_listrik_di_garasi_listrik(): void
    {
        $this->expectExceptionMessage('Persentase baterai saat ini wajib diisi.');

        RentalService::create($this->tenant, $this->evData(['current_battery_percent' => null]));
    }

    public function test_persentase_baterai_harus_antara_0_dan_100(): void
    {
        $this->expectExceptionMessage('Persentase baterai harus antara 0 dan 100.');

        RentalService::create($this->tenant, $this->evData(['current_battery_percent' => 150]));
    }

    public function test_kendaraan_tidak_boleh_di_tipe_garasi_yang_salah(): void
    {
        $this->expectExceptionMessage('Kendaraan ini tidak bisa diparkir di tipe garasi tersebut.');

        RentalService::create($this->tenant, $this->motorData(['garage_id' => $this->carGarage->id]));
    }

    public function test_mobil_tidak_boleh_di_garasi_mobil_listrik(): void
    {
        $this->expectExceptionMessage('Kendaraan ini tidak bisa diparkir di tipe garasi tersebut.');

        RentalService::create($this->tenant, $this->motorData([
            'vehicle' => ['type' => 'mobil', 'plate_number' => 'B 1 AA', 'brand' => 'Toyota', 'model' => 'Avanza'],
            'garage_id' => $this->evGarage->id,
        ]));
    }

    public function test_penyewa_yang_belum_disetujui_tidak_bisa_menyewa(): void
    {
        $this->expectExceptionMessage('Akun Anda belum disetujui admin.');

        RentalService::create(User::factory()->pending()->create(), $this->motorData());
    }

    public function test_admin_tidak_bisa_menyewa(): void
    {
        $this->expectExceptionMessage('Akun Anda belum disetujui admin.');

        RentalService::create($this->makeAdmin(), $this->motorData());
    }

    public function test_bukti_pelunasan_wajib(): void
    {
        $this->expectExceptionMessage('Bukti pelunasan biaya sewa wajib diupload.');

        RentalService::create($this->tenant, $this->motorData(['payment_proof_path' => '']));
    }

    public function test_garasi_tidak_ditemukan(): void
    {
        $this->expectExceptionMessage('Garasi tidak ditemukan.');

        RentalService::create($this->tenant, $this->motorData(['garage_id' => '00000000-0000-0000-0000-000000000000']));
    }

    public function test_tipe_sewa_tidak_valid(): void
    {
        $this->expectExceptionMessage('Tipe sewa tidak valid.');

        RentalService::create($this->tenant, $this->motorData(['rental_type' => 'tahunan']));
    }

    public function test_garasi_yang_sudah_disewa_tidak_bisa_disewa_lagi_dan_data_tidak_tersimpan(): void
    {
        $other = $this->makeTenant();
        $this->makeRental($other, $this->makeVehicle($other, plate: 'L 9999 ZZ'), $this->bikeGarage, '2026-11-12', '2026-11-14');

        try {
            RentalService::create($this->tenant, $this->motorData());
            $this->fail('Seharusnya melempar GeneralException.');
        } catch (GeneralException $e) {
            $this->assertSame('Garasi sudah disewa pada tanggal tersebut. Silakan pilih garasi lain.', $e->getMessage());
        }

        // Kendaraan yang sempat dibuat ikut dibatalkan (DB::transaction).
        $this->assertDatabaseMissing('vehicles', ['plate_number' => 'L 5678 CD']);
        $this->assertDatabaseCount('rentals', 1);
    }

    public function test_garasi_bisa_disewa_setelah_sewa_sebelumnya_selesai(): void
    {
        $other = $this->makeTenant();
        $this->makeRental($other, $this->makeVehicle($other, plate: 'L 9999 ZZ'), $this->bikeGarage, '2026-11-05', '2026-11-09');

        $rental = RentalService::create($this->tenant, $this->motorData());

        $this->assertTrue($rental->exists);
    }

    public function test_kendaraan_yang_sedang_disewa_di_garasi_lain_ditolak(): void
    {
        $vehicle = $this->makeVehicle($this->tenant, VehicleType::SepedaMotor, 'L 5678 CD');
        $this->makeRental($this->tenant, $vehicle, $this->makeGarage(GarageType::Sepeda, 2), '2026-11-10', '2026-11-11');

        $this->expectExceptionMessage('Kendaraan ini masih tercatat di sewa lain pada tanggal tersebut.');

        RentalService::create($this->tenant, $this->motorData());
    }

    public function test_satu_penyewa_boleh_menyewa_lebih_dari_satu_garasi(): void
    {
        RentalService::create($this->tenant, $this->motorData());
        RentalService::create($this->tenant, $this->evData());

        $this->assertSame(2, Rental::query()->where('user_id', $this->tenant->id)->count());
    }

    // ================= preview() =================

    public function test_preview_menghitung_tanpa_menyimpan(): void
    {
        $preview = RentalService::preview([
            'garage_id' => $this->evGarage->id,
            'rental_type' => 'harian',
            'days' => 2,
            'vehicle_type' => 'mobil_listrik',
            'max_battery_capacity_kwh' => 60,
            'current_battery_percent' => 40,
        ]);

        $this->assertSame([
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-11',
            'days' => 2,
            'daily_price' => 50000.0,
            'rent_cost' => 100000.0,
            'charge_needed_kwh' => 36.0,
            'charging_cost' => 90000.0,
            'total_cost' => 190000.0,
        ], $preview);
        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_preview_tanpa_data_baterai_biaya_listrik_nol(): void
    {
        $preview = RentalService::preview([
            'garage_id' => $this->evGarage->id,
            'rental_type' => 'bulanan',
            'vehicle_type' => 'mobil_listrik',
        ]);

        $this->assertSame(0.0, $preview['charging_cost']);
        $this->assertSame(1250000.0, $preview['total_cost']); // 25 x 50.000
    }

    public function test_preview_tanpa_tipe_kendaraan_tidak_cek_kecocokan(): void
    {
        $preview = RentalService::preview([
            'garage_id' => $this->carGarage->id,
            'rental_type' => 'harian',
            'days' => 1,
        ]);

        $this->assertSame(30000.0, $preview['total_cost']);
    }

    public function test_preview_menolak_tipe_garasi_yang_salah(): void
    {
        $this->expectExceptionMessage('Kendaraan ini tidak bisa diparkir di tipe garasi tersebut.');

        RentalService::preview([
            'garage_id' => $this->carGarage->id,
            'rental_type' => 'harian',
            'days' => 1,
            'vehicle_type' => 'sepeda',
        ]);
    }

    public function test_preview_garasi_tidak_ditemukan(): void
    {
        $this->expectExceptionMessage('Garasi tidak ditemukan.');

        RentalService::preview(['rental_type' => 'harian', 'days' => 1]);
    }

    // ================= tenantRentals() =================

    public function test_tenant_rentals_hanya_sewa_baru_milik_sendiri_terbaru_dulu(): void
    {
        $vehicle = $this->makeVehicle($this->tenant);
        $old = $this->makeRental($this->tenant, $vehicle, $this->bikeGarage, '2026-11-01', '2026-11-03');
        $new = $this->makeRental($this->tenant, $vehicle, $this->bikeGarage, '2026-11-10', '2026-11-11');
        $this->makeExtension($new, '2026-11-12', '2026-11-13');

        $other = $this->makeTenant();
        $this->makeRental($other, $this->makeVehicle($other, plate: 'L 9 Z'), $this->carGarage, '2026-11-10', '2026-11-10');

        $rentals = RentalService::tenantRentals($this->tenant);

        $this->assertSame([$new->id, $old->id], $rentals->pluck('id')->all());
        $this->assertTrue($rentals->first()->relationLoaded('extensions'));
        $this->assertCount(1, $rentals->first()->extensions);
    }

    // ================= activeRentals() =================

    public function test_active_rentals_berisi_sewa_yang_berjalan_hari_ini(): void
    {
        $v1 = $this->makeVehicle($this->tenant, plate: 'A 1');
        $v2 = $this->makeVehicle($this->tenant, plate: 'A 2');
        $v3 = $this->makeVehicle($this->tenant, plate: 'A 3');
        $v4 = $this->makeVehicle($this->tenant, plate: 'A 4');
        $v5 = $this->makeVehicle($this->tenant, plate: 'A 5');
        $s2 = $this->makeGarage(GarageType::Sepeda, 2);
        $s3 = $this->makeGarage(GarageType::Sepeda, 3);
        $s4 = $this->makeGarage(GarageType::Sepeda, 4);
        $s5 = $this->makeGarage(GarageType::Sepeda, 5);

        $endsToday = $this->makeRental($this->tenant, $v2, $s2, '2026-11-08', '2026-11-10');
        $running = $this->makeRental($this->tenant, $v1, $this->bikeGarage, '2026-11-09', '2026-11-12');
        $this->makeRental($this->tenant, $v3, $s3, '2026-11-01', '2026-11-09');             // sudah selesai
        $this->makeRental($this->tenant, $v4, $s4, '2026-11-11', '2026-11-12');             // belum mulai
        $extended = $this->makeRental($this->tenant, $v5, $s5, '2026-11-01', '2026-11-09'); // diperpanjang
        $this->makeExtension($extended, '2026-11-10', '2026-11-11');

        $active = RentalService::activeRentals();

        // urut kode garasi: S001, S002, S005. Baris extend tidak muncul sendiri.
        $this->assertSame([$running->id, $endsToday->id, $extended->id], $active->pluck('id')->all());
    }

    // ================= originalOf() & latestEndDate() =================

    public function test_original_of_mengembalikan_sewa_awal(): void
    {
        $original = $this->makeRental($this->tenant, $this->makeVehicle($this->tenant), $this->bikeGarage, '2026-11-08', '2026-11-10');
        $extension = $this->makeExtension($original, '2026-11-11', '2026-11-12');

        $this->assertSame($original->id, RentalService::originalOf($original)->id);
        $this->assertSame($original->id, RentalService::originalOf($extension)->id);
    }

    public function test_original_of_error_kalau_parent_tidak_ada(): void
    {
        $orphan = $this->makeRental($this->tenant, $this->makeVehicle($this->tenant), $this->bikeGarage, '2026-11-08', '2026-11-10', [
            'status' => RentalStatus::Extend,
        ]);

        $this->expectExceptionMessage('Data sewa awal tidak ditemukan.');

        RentalService::originalOf($orphan);
    }

    public function test_latest_end_date_memperhitungkan_perpanjangan(): void
    {
        $original = $this->makeRental($this->tenant, $this->makeVehicle($this->tenant), $this->bikeGarage, '2026-11-08', '2026-11-10');
        $this->assertSame('2026-11-10', RentalService::latestEndDate($original)->toDateString());

        $this->makeExtension($original, '2026-11-11', '2026-11-12');
        $last = $this->makeExtension($original, '2026-11-13', '2026-11-15');

        $this->assertSame('2026-11-15', RentalService::latestEndDate($original->fresh())->toDateString());
        $this->assertSame('2026-11-15', RentalService::latestEndDate($last)->toDateString());
        $this->assertSame('2026-11-15', RentalService::latestEndDate($original->fresh()->load('extensions'))->toDateString());
    }

    // ================= previewExtension() & extend() =================

    private function runningRental(): Rental
    {
        return $this->makeRental($this->tenant, $this->makeVehicle($this->tenant), $this->carGarage, '2026-11-08', '2026-11-12');
    }

    public function test_preview_perpanjangan_menghitung_sisa_biaya(): void
    {
        $preview = RentalService::previewExtension($this->runningRental(), '2026-11-15');

        $this->assertSame([
            'current_end_date' => '2026-11-12',
            'start_date' => '2026-11-13',
            'end_date' => '2026-11-15',
            'extra_days' => 3,
            'daily_price' => 30000.0,
            'rent_cost' => 90000.0,
        ], $preview);
        $this->assertDatabaseCount('rentals', 1);
    }

    public function test_perpanjangan_membuat_baris_extend_baru(): void
    {
        $original = $this->runningRental();

        $extension = RentalService::extend($original, '2026-11-15', 'proofs/perpanjang.jpg');

        $this->assertSame(RentalStatus::Extend, $extension->status);
        $this->assertSame($original->id, $extension->parent_id);
        $this->assertSame(RentalType::Harian, $extension->rental_type);
        $this->assertSame(3, $extension->duration_days);
        $this->assertSame('2026-11-13', $extension->start_date->toDateString());
        $this->assertSame('2026-11-15', $extension->end_date->toDateString());
        $this->assertEquals(90000, $extension->rent_cost);
        $this->assertSame('proofs/perpanjang.jpg', $extension->payment_proof);
        $this->assertSame($original->garage_id, $extension->garage_id);
        $this->assertSame($original->vehicle_id, $extension->vehicle_id);
    }

    public function test_perpanjangan_sewa_bulanan_tetap_dihitung_harian(): void
    {
        $original = $this->makeRental($this->tenant, $this->makeVehicle($this->tenant), $this->carGarage, '2026-11-10', '2026-12-09', [
            'rental_type' => RentalType::Bulanan,
            'rent_cost' => 750000,
        ]);

        $extension = RentalService::extend($original, '2026-12-11', 'proofs/x.jpg');

        $this->assertSame(RentalType::Harian, $extension->rental_type);
        $this->assertEquals(60000, $extension->rent_cost); // 2 x 30.000
    }

    public function test_perpanjangan_kedua_dimulai_setelah_perpanjangan_pertama(): void
    {
        $original = $this->runningRental();
        $first = RentalService::extend($original, '2026-11-15', 'proofs/1.jpg');

        // dipanggil dengan baris extend: tetap menempel ke sewa awal
        $second = RentalService::extend($first, '2026-11-17', 'proofs/2.jpg');

        $this->assertSame($original->id, $second->parent_id);
        $this->assertSame('2026-11-16', $second->start_date->toDateString());
        $this->assertSame(2, $second->duration_days);
    }

    public function test_perpanjangan_wajib_bukti_bayar(): void
    {
        $this->expectExceptionMessage('Bukti pembayaran perpanjangan wajib diupload.');

        RentalService::extend($this->runningRental(), '2026-11-15', '');
    }

    public function test_tanggal_selesai_baru_harus_setelah_tanggal_selesai_lama(): void
    {
        $this->expectExceptionMessage('Tanggal selesai baru harus setelah 12-11-2026.');

        RentalService::previewExtension($this->runningRental(), '2026-11-12');
    }

    public function test_sewa_yang_sudah_berakhir_tidak_bisa_diperpanjang(): void
    {
        $ended = $this->makeRental($this->tenant, $this->makeVehicle($this->tenant), $this->carGarage, '2026-11-01', '2026-11-09');

        $this->expectExceptionMessage('Sewa ini sudah berakhir sehingga tidak bisa diperpanjang.');

        RentalService::extend($ended, '2026-11-15', 'proofs/x.jpg');
    }

    public function test_sewa_yang_selesai_hari_ini_masih_bisa_diperpanjang(): void
    {
        $endsToday = $this->makeRental($this->tenant, $this->makeVehicle($this->tenant), $this->carGarage, '2026-11-08', '2026-11-10');

        $extension = RentalService::extend($endsToday, '2026-11-11', 'proofs/x.jpg');

        $this->assertSame('2026-11-11', $extension->start_date->toDateString());
    }

    public function test_perpanjangan_ditolak_kalau_garasi_sudah_dipesan_penyewa_lain(): void
    {
        $original = $this->runningRental();
        $other = $this->makeTenant();
        $this->makeRental($other, $this->makeVehicle($other, plate: 'L 9 Z'), $this->carGarage, '2026-11-14', '2026-11-16');

        $this->expectExceptionMessage('Garasi sudah dipesan penyewa lain pada rentang tanggal tersebut.');

        RentalService::extend($original, '2026-11-15', 'proofs/x.jpg');
    }

    public function test_perpanjangan_ditolak_kalau_kendaraan_dipakai_di_sewa_lain(): void
    {
        $original = $this->runningRental();
        $this->makeRental($this->tenant, $original->vehicle, $this->makeGarage(GarageType::Mobil, 2, 30000), '2026-11-14', '2026-11-16');

        $this->expectExceptionMessage('Kendaraan ini tercatat di sewa lain pada rentang tanggal tersebut.');

        RentalService::extend($original, '2026-11-15', 'proofs/x.jpg');
    }
}
