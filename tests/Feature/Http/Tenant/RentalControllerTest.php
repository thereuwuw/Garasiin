<?php

namespace Tests\Feature\Http\Tenant;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesRentalData;
use Tests\TestCase;

class RentalControllerTest extends TestCase
{
    use CreatesRentalData, RefreshDatabase;

    private User $tenant;

    private Garage $bike;

    private Garage $ev;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-10 09:00:00');
        Storage::fake('public');

        $this->tenant = $this->makeTenant();
        $this->bike = $this->makeGarage(GarageType::Sepeda, 1, 5000);
        $this->ev = $this->makeGarage(GarageType::MobilListrik, 1, 50000, 2500);

        $this->actingAs($this->tenant);
    }

    private function motorForm(array $overrides = []): array
    {
        return array_merge([
            'vehicle_type' => 'sepeda_motor',
            'plate_number' => 'L 5678 CD',
            'brand' => 'Honda',
            'model' => 'Vario 160',
            'rental_type' => 'harian',
            'start_date' => '2026-11-10',
            'days' => 3,
            'garage_id' => $this->bike->id,
            'payment_proof' => UploadedFile::fake()->image('bayar.jpg'),
        ], $overrides);
    }

    // ---------- index() & create() ----------

    public function test_daftar_sewa_hanya_milik_sendiri(): void
    {
        $mine = $this->makeRental($this->tenant, $this->makeVehicle($this->tenant), $this->bike, '2026-11-10', '2026-11-11');
        $other = $this->makeTenant();
        $this->makeRental($other, $this->makeVehicle($other, plate: 'L 9 Z'), $this->ev, '2026-11-10', '2026-11-11');

        $this->get(route('tenant.rentals.index'))
            ->assertOk()
            ->assertSee('S001')
            ->assertViewHas('rentals', fn ($rentals) => $rentals->pluck('id')->all() === [$mine->id]);
    }

    public function test_form_sewa_baru(): void
    {
        $this->get(route('tenant.rentals.create'))
            ->assertOk()
            ->assertViewHas('startDate', '2026-11-10')
            ->assertViewHas('maxDailyDays', 29)
            ->assertViewHas('garageTypesByVehicle', fn ($map) => $map['mobil_listrik'] === ['mobil', 'mobil_listrik']);
    }

    // ---------- availableGarages() ----------

    public function test_tipe_garasi_yang_boleh_sesuai_tipe_kendaraan(): void
    {
        $this->getJson(route('tenant.rentals.garages', ['vehicle_type' => 'sepeda_motor']))
            ->assertOk()
            ->assertExactJson(['allowed_types' => ['sepeda'], 'garages' => []]);

        $this->getJson(route('tenant.rentals.garages', ['vehicle_type' => 'mobil_listrik']))
            ->assertJson(['allowed_types' => ['mobil', 'mobil_listrik']]);
    }

    public function test_tipe_garasi_yang_tidak_cocok_tidak_mengembalikan_garasi(): void
    {
        $this->getJson(route('tenant.rentals.garages', [
            'vehicle_type' => 'mobil',
            'garage_type' => 'sepeda',
            'rental_type' => 'harian',
            'days' => 1,
        ]))->assertJson(['garages' => []]);
    }

    public function test_daftar_garasi_tersedia_pada_periode(): void
    {
        $booked = $this->makeGarage(GarageType::Sepeda, 2);
        $other = $this->makeTenant();
        $this->makeRental($other, $this->makeVehicle($other, plate: 'L 9 Z'), $booked, '2026-11-11', '2026-11-11');

        $this->getJson(route('tenant.rentals.garages', [
            'vehicle_type' => 'sepeda_motor',
            'garage_type' => 'sepeda',
            'rental_type' => 'harian',
            'days' => 3,
        ]))
            ->assertOk()
            ->assertJsonPath('end_date', '2026-11-12')
            ->assertJsonCount(1, 'garages')
            ->assertJsonPath('garages.0.code', 'S001')
            ->assertJsonPath('garages.0.type_label', 'Garasi Sepeda')
            ->assertJsonPath('garages.0.electricity_price_per_kwh', null);
    }

    public function test_garasi_listrik_menyertakan_harga_listrik(): void
    {
        $this->getJson(route('tenant.rentals.garages', [
            'vehicle_type' => 'mobil_listrik',
            'garage_type' => 'mobil_listrik',
            'rental_type' => 'bulanan',
        ]))
            ->assertJsonPath('end_date', '2026-12-09')
            ->assertJsonPath('garages.0.electricity_price_per_kwh', 2500);
    }

    public function test_jumlah_hari_melebihi_batas_422(): void
    {
        $this->getJson(route('tenant.rentals.garages', [
            'vehicle_type' => 'sepeda_motor',
            'garage_type' => 'sepeda',
            'rental_type' => 'harian',
            'days' => 30,
        ]))
            ->assertStatus(422)
            ->assertJson(['message' => 'Jumlah hari harus antara 1 dan 29 untuk bulan ini.']);
    }

    public function test_tanggal_mulai_kemarin_ditolak(): void
    {
        $this->getJson(route('tenant.rentals.garages', [
            'vehicle_type' => 'sepeda_motor',
            'start_date' => '2026-11-09',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date' => 'Tanggal mulai tidak boleh sebelum hari ini.']);
    }

    // ---------- preview() ----------

    public function test_preview_total_biaya(): void
    {
        $this->postJson(route('tenant.rentals.preview'), [
            'garage_id' => $this->ev->id,
            'rental_type' => 'harian',
            'days' => 2,
            'vehicle_type' => 'mobil_listrik',
            'max_battery_capacity_kwh' => 60,
            'current_battery_percent' => 40,
        ])
            ->assertOk()
            ->assertJson(['rent_cost' => 100000, 'charging_cost' => 90000, 'total_cost' => 190000]);
    }

    public function test_preview_error_422(): void
    {
        $this->postJson(route('tenant.rentals.preview'), ['rental_type' => 'harian'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Garasi tidak ditemukan.']);
    }

    // ---------- store() ----------

    public function test_penyewa_menyimpan_sewa_baru(): void
    {
        $this->post(route('tenant.rentals.store'), $this->motorForm())
            ->assertRedirect(route('tenant.rentals.index'))
            ->assertSessionHas('success', 'Sewa garasi berhasil disimpan.');

        $rental = Rental::first();
        $this->assertSame($this->tenant->id, $rental->user_id);
        $this->assertEquals(15000, $rental->rent_cost);
        Storage::disk('public')->assertExists($rental->payment_proof);
        $this->assertSame(GarageStatus::Used, $this->bike->fresh()->status);
    }

    public function test_sepeda_tidak_perlu_plat(): void
    {
        $this->post(route('tenant.rentals.store'), $this->motorForm([
            'vehicle_type' => 'sepeda',
            'plate_number' => null,
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Rental::first()->vehicle->plate_number);
    }

    public function test_mobil_listrik_menyimpan_data_isi_listrik(): void
    {
        $this->post(route('tenant.rentals.store'), $this->motorForm([
            'vehicle_type' => 'mobil_listrik',
            'plate_number' => 'L 1234 AB',
            'brand' => 'Hyundai',
            'model' => 'Ioniq 5',
            'max_battery_capacity_kwh' => 60,
            'current_battery_percent' => 40,
            'garage_id' => $this->ev->id,
            'charging_payment_proof' => UploadedFile::fake()->image('listrik.jpg'),
        ]))->assertSessionHasNoErrors();

        $charging = Rental::first()->charging;
        $this->assertEquals(90000, $charging->charging_cost);
        Storage::disk('public')->assertExists($charging->payment_proof);
    }

    public function test_validasi_form_sewa_baru(): void
    {
        $this->post(route('tenant.rentals.store'), $this->motorForm([
            'plate_number' => null,
            'brand' => '',
            'days' => null,
            'start_date' => '2026-11-09',
            'garage_id' => 'bukan-uuid',
            'payment_proof' => null,
        ]))->assertSessionHasErrors(['plate_number', 'brand', 'days', 'start_date', 'garage_id', 'payment_proof']);

        $this->post(route('tenant.rentals.store'), $this->motorForm([
            'vehicle_type' => 'mobil_listrik',
            'garage_id' => $this->ev->id,
        ]))->assertSessionHasErrors('max_battery_capacity_kwh');

        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_bukti_pelunasan_selain_gambar_ditolak(): void
    {
        $this->post(route('tenant.rentals.store'), $this->motorForm([
            'payment_proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('payment_proof');

        $this->post(route('tenant.rentals.store'), $this->motorForm([
            'payment_proof' => UploadedFile::fake()->create('bukti.txt', 1, 'text/plain'),
        ]))->assertSessionHasErrors('payment_proof');

        $this->assertDatabaseCount('rentals', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_bukti_bayar_listrik_selain_gambar_ditolak(): void
    {
        $this->post(route('tenant.rentals.store'), $this->motorForm([
            'vehicle_type' => 'mobil_listrik',
            'plate_number' => 'L 1234 AB',
            'max_battery_capacity_kwh' => 60,
            'current_battery_percent' => 40,
            'garage_id' => $this->ev->id,
            'charging_payment_proof' => UploadedFile::fake()->create('listrik.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('charging_payment_proof');

        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_sewa_bulanan_tidak_perlu_jumlah_hari(): void
    {
        $this->post(route('tenant.rentals.store'), $this->motorForm([
            'rental_type' => 'bulanan',
            'days' => null,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(30, Rental::first()->duration_days);
    }

    public function test_gagal_dari_service_file_upload_dihapus_lagi(): void
    {
        // Sepeda motor ke garasi mobil listrik: lolos validasi form, ditolak service.
        $this->post(route('tenant.rentals.store'), $this->motorForm(['garage_id' => $this->ev->id]))
            ->assertSessionHasErrors(['rental' => 'Kendaraan ini tidak bisa diparkir di tipe garasi tersebut.']);

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('rentals', 0);
    }
}
