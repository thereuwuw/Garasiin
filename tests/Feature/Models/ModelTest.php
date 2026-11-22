<?php

namespace Tests\Feature\Models;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\RentalStatus;
use App\Enums\RentalType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRentalData;
use Tests\TestCase;

class ModelTest extends TestCase
{
    use CreatesRentalData, RefreshDatabase;

    // ---------- User ----------

    public function test_user_role_dan_status_tidak_bisa_diisi_lewat_mass_assignment(): void
    {
        $user = new User(['name' => 'X', 'role' => 'admin', 'status' => 'accepted', 'verified_by' => 'abc']);

        $this->assertNull($user->role);
        $this->assertNull($user->status);
        $this->assertNull($user->verified_by);
    }

    public function test_user_cast_ke_enum_dan_password_tersembunyi(): void
    {
        $user = $this->makeTenant()->fresh();

        $this->assertSame(UserRole::Penyewa, $user->role);
        $this->assertSame(UserStatus::Accepted, $user->status);
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    public function test_relasi_user_kendaraan_sewa_dan_verifikator(): void
    {
        $admin = $this->makeAdmin();
        $tenant = $this->makeTenant();
        $tenant->forceFill(['verified_by' => $admin->id])->save();
        $vehicle = $this->makeVehicle($tenant);
        $rental = $this->makeRental($tenant, $vehicle, $this->makeGarage(), '2026-11-10', '2026-11-10');

        $this->assertTrue($tenant->vehicles->contains($vehicle));
        $this->assertTrue($tenant->rentals->contains($rental));
        $this->assertTrue($tenant->verifier->is($admin));
        $this->assertTrue($admin->verifiedUsers->contains($tenant));
    }

    // ---------- Garage ----------

    public function test_garasi_baru_default_available_dan_cast_enum(): void
    {
        $garage = $this->makeGarage(GarageType::MobilListrik, 1, 50000, 2500)->fresh();

        $this->assertSame(GarageStatus::Available, $garage->status);
        $this->assertSame(GarageType::MobilListrik, $garage->type);
        $this->assertSame(1, $garage->sequence);
        $this->assertSame('50000.00', $garage->daily_price);
        $this->assertSame('2500.00', $garage->electricity_price_per_kwh);
    }

    public function test_status_garasi_tidak_bisa_diisi_lewat_mass_assignment(): void
    {
        $garage = new Garage(['status' => 'used']);

        $this->assertSame('available', $garage->getAttributes()['status']);
    }

    // ---------- Vehicle ----------

    public function test_relasi_dan_cast_kendaraan(): void
    {
        $tenant = $this->makeTenant();
        $vehicle = $this->makeVehicle($tenant, VehicleType::MobilListrik, 'L 1 A', 60)->fresh();
        $rental = $this->makeRental($tenant, $vehicle, $this->makeGarage(GarageType::Mobil), '2026-11-10', '2026-11-10');

        $this->assertSame(VehicleType::MobilListrik, $vehicle->type);
        $this->assertSame('60.00', $vehicle->max_battery_capacity_kwh);
        $this->assertTrue($vehicle->user->is($tenant));
        $this->assertTrue($vehicle->rentals->contains($rental));
    }

    // ---------- Rental & Charging ----------

    public function test_relasi_dan_cast_sewa(): void
    {
        $tenant = $this->makeTenant();
        $vehicle = $this->makeVehicle($tenant);
        $garage = $this->makeGarage();
        $rental = $this->makeRental($tenant, $vehicle, $garage, '2026-11-10', '2026-11-12')->fresh();
        $extension = $this->makeExtension($rental, '2026-11-13', '2026-11-14');
        $charging = $rental->charging()->create([
            'current_battery_percent' => 40,
            'charge_needed_kwh' => 36,
            'charging_cost' => 90000,
            'payment_proof' => 'proofs/l.jpg',
        ]);

        $this->assertSame(RentalStatus::New, $rental->status);
        $this->assertSame(RentalType::Harian, $rental->rental_type);
        $this->assertInstanceOf(CarbonInterface::class, $rental->start_date);
        $this->assertSame(3, $rental->duration_days);
        $this->assertTrue($rental->user->is($tenant));
        $this->assertTrue($rental->vehicle->is($vehicle));
        $this->assertTrue($rental->garage->is($garage));
        $this->assertTrue($rental->extensions->contains($extension));
        $this->assertTrue($extension->parent->is($rental));
        $this->assertTrue($rental->charging->is($charging));
        $this->assertTrue($charging->rental->is($rental));
        $this->assertTrue($garage->rentals->contains($rental));
        $this->assertSame('90000.00', $charging->fresh()->charging_cost);
    }
}
