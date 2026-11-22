<?php

namespace Tests\Feature\Http\Tenant;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesRentalData;
use Tests\TestCase;

/**
 * Penyewa A tidak boleh melihat atau mengubah transaksi penyewa B,
 * baik lewat ID di URL maupun lewat isi request.
 */
class TenantIsolationTest extends TestCase
{
    use CreatesRentalData, RefreshDatabase;

    private User $tenantA;

    private User $tenantB;

    private Vehicle $vehicleB;

    private Garage $garage;

    private Rental $rentalB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-10 09:00:00');
        Storage::fake('public');

        $this->tenantA = $this->makeTenant(['name' => 'Penyewa A']);
        $this->tenantB = $this->makeTenant(['name' => 'Penyewa B']);
        $this->garage = $this->makeGarage(GarageType::Sepeda, 1, 5000);
        $this->vehicleB = $this->makeVehicle($this->tenantB, VehicleType::SepedaMotor, 'L 7777 BB');
        $this->rentalB = $this->makeRental($this->tenantB, $this->vehicleB, $this->garage, '2026-11-10', '2026-11-12');

        $this->actingAs($this->tenantA);
    }

    private function formA(array $overrides = []): array
    {
        return array_merge([
            'vehicle_type' => 'sepeda_motor',
            'plate_number' => 'L 1111 AA',
            'brand' => 'Honda',
            'model' => 'Beat',
            'rental_type' => 'harian',
            'start_date' => '2026-11-10',
            'days' => 2,
            'garage_id' => $this->makeGarage(GarageType::Sepeda, 2, 5000)->id,
            'payment_proof' => UploadedFile::fake()->image('bayar.jpg'),
        ], $overrides);
    }

    public function test_daftar_sewa_a_tidak_menampilkan_sewa_b(): void
    {
        $this->get(route('tenant.rentals.index'))
            ->assertOk()
            ->assertDontSee('L 7777 BB')
            ->assertViewHas('rentals', fn ($rentals) => $rentals->isEmpty());
    }

    public function test_a_tidak_bisa_membuka_atau_memperpanjang_sewa_b_lewat_id_di_url(): void
    {
        // Halaman perpanjangan memakai ID sewa di URL, tapi khusus admin.
        $this->get(route('admin.extensions.edit', $this->rentalB))->assertForbidden();
        $this->post(route('admin.extensions.preview', $this->rentalB), ['new_end_date' => '2026-11-15'])->assertForbidden();
        $this->post(route('admin.extensions.store', $this->rentalB), [
            'new_end_date' => '2026-11-15',
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertForbidden();

        $this->assertDatabaseCount('rentals', 1);
        $this->assertSame('2026-11-12', $this->rentalB->fresh()->end_date->toDateString());
    }

    public function test_a_tidak_bisa_melihat_laporan_transaksi_b(): void
    {
        $this->get(route('admin.reports.index', ['user_id' => $this->tenantB->id]))->assertForbidden();
    }

    public function test_a_tidak_bisa_memverifikasi_akun_lewat_id_di_url(): void
    {
        $pending = User::factory()->pending()->create();

        $this->patch(route('admin.verifications.accept', $pending))->assertForbidden();

        $this->assertSame('pending', $pending->fresh()->status->value);
    }

    public function test_user_id_di_request_diabaikan_sewa_tetap_milik_a(): void
    {
        $this->post(route('tenant.rentals.store'), $this->formA([
            'user_id' => $this->tenantB->id,
        ]))->assertSessionHasNoErrors();

        $rental = Rental::query()->where('user_id', '!=', $this->tenantB->id)->first();
        $this->assertNotNull($rental);
        $this->assertSame($this->tenantA->id, $rental->user_id);
        $this->assertSame($this->tenantA->id, $rental->vehicle->user_id);
        $this->assertSame(1, Rental::query()->where('user_id', $this->tenantB->id)->count());
    }

    public function test_a_tidak_bisa_memakai_kendaraan_b_lewat_nomor_plat(): void
    {
        $this->post(route('tenant.rentals.store'), $this->formA([
            'plate_number' => 'l 7777 bb', // plat milik B, ditulis beda format
            'start_date' => '2026-11-20',
        ]))->assertSessionHasErrors(['rental' => 'Nomor plat ini sudah terdaftar atas penyewa lain.']);

        $this->assertDatabaseCount('rentals', 1);
        $this->assertSame('Vario', $this->vehicleB->fresh()->model); // data kendaraan B tidak berubah
    }

    public function test_a_tidak_bisa_menyewa_garasi_yang_sedang_disewa_b(): void
    {
        $this->post(route('tenant.rentals.store'), $this->formA([
            'garage_id' => $this->garage->id,
        ]))->assertSessionHasErrors(['rental' => 'Garasi sudah disewa pada tanggal tersebut. Silakan pilih garasi lain.']);

        $this->assertDatabaseCount('rentals', 1);
    }

    public function test_daftar_garasi_tersedia_tidak_membocorkan_data_sewa_b(): void
    {
        $this->getJson(route('tenant.rentals.garages', [
            'vehicle_type' => 'sepeda_motor',
            'garage_type' => 'sepeda',
            'rental_type' => 'harian',
            'days' => 1,
        ]))
            ->assertOk()
            ->assertJsonMissing(['code' => 'S001'])
            ->assertDontSee($this->tenantB->name)
            ->assertDontSee('L 7777 BB');
    }
}
