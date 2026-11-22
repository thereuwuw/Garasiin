<?php

namespace Tests\Concerns;

use App\Enums\GarageType;
use App\Enums\RentalStatus;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Helper untuk membuat data uji (garasi, kendaraan, sewa) dengan cepat.
 * Project belum punya factory untuk Garage/Vehicle/Rental, jadi helper ini menggantikannya.
 */
trait CreatesRentalData
{
    protected function makeAdmin(array $attributes = []): User
    {
        return User::factory()->admin()->create($attributes);
    }

    protected function makeTenant(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function makeGarage(
        GarageType $type = GarageType::Sepeda,
        int $sequence = 1,
        float $dailyPrice = 5000,
        ?float $electricityPrice = null,
    ): Garage {
        return Garage::create([
            'code' => $type->prefix().str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            'type' => $type,
            'sequence' => $sequence,
            'daily_price' => $dailyPrice,
            'electricity_price_per_kwh' => $electricityPrice,
        ]);
    }

    protected function makeVehicle(
        User $user,
        VehicleType $type = VehicleType::SepedaMotor,
        ?string $plate = 'L 1111 AA',
        ?float $capacity = null,
    ): Vehicle {
        return Vehicle::create([
            'user_id' => $user->id,
            'type' => $type,
            'plate_number' => $plate,
            'brand' => 'Honda',
            'model' => 'Vario',
            'max_battery_capacity_kwh' => $capacity,
        ]);
    }

    /**
     * Buat baris sewa langsung ke database (tanpa lewat RentalService).
     */
    protected function makeRental(
        User $user,
        Vehicle $vehicle,
        Garage $garage,
        string $start,
        string $end,
        array $attributes = [],
    ): Rental {
        $days = (int) \Illuminate\Support\Carbon::parse($start)->diffInDays($end) + 1;

        return Rental::create(array_merge([
            'parent_id' => null,
            'status' => RentalStatus::New,
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'rental_type' => RentalType::Harian,
            'duration_days' => $days,
            'start_date' => $start,
            'end_date' => $end,
            'rent_cost' => $days * (float) $garage->daily_price,
            'payment_proof' => 'proofs/sample.jpg',
        ], $attributes));
    }

    /**
     * Buat baris perpanjangan (status extend) untuk sebuah sewa.
     */
    protected function makeExtension(Rental $original, string $start, string $end): Rental
    {
        return $this->makeRental(
            $original->user,
            $original->vehicle,
            $original->garage,
            $start,
            $end,
            ['parent_id' => $original->id, 'status' => RentalStatus::Extend],
        );
    }
}
