<?php

namespace Database\Seeders;

use App\Enums\GarageStatus;
use App\Enums\RentalStatus;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Models\Charging;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class RentalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bambang = User::where('email', 'bambang@gmail.com')->firstOrFail();
        $garageE001 = Garage::where('code', 'E001')->firstOrFail();
        $garageS001 = Garage::where('code', 'S001')->firstOrFail();
        $today = CarbonImmutable::today();

        // Kendaraan milik Bambang
        $ioniq = Vehicle::create([
            'user_id' => $bambang->id,
            'type' => VehicleType::MobilListrik,
            'plate_number' => 'L 1234 AB',
            'brand' => 'Hyundai',
            'model' => 'Ioniq 5',
            'max_battery_capacity_kwh' => 60,
        ]);

        $vario = Vehicle::create([
            'user_id' => $bambang->id,
            'type' => VehicleType::SepedaMotor,
            'plate_number' => 'L 5678 CD',
            'brand' => 'Honda',
            'model' => 'Vario 160',
            'max_battery_capacity_kwh' => null,
        ]);

        // 1) Sewa bulanan Ioniq 5 di E001
        $monthlyDays = $today->daysInMonth;   // jumlah hari di bulan mulai sewa
        $ioniqRental = Rental::create([
            'parent_id' => null,
            'status' => RentalStatus::New,
            'user_id' => $bambang->id,
            'vehicle_id' => $ioniq->id,
            'garage_id' => $garageE001->id,
            'rental_type' => RentalType::Bulanan,
            'duration_days' => $monthlyDays,
            'start_date' => $today,
            'end_date' => $today->addDays($monthlyDays - 1),
            'rent_cost' => 25 * 50000,        // 1.250.000
            'payment_proof' => 'proofs/sample-rental.jpg',
        ]);

        // Charging: 60 kWh x (100 - 60) / 100 = 24 kWh x 2.500 = 60.000
        Charging::create([
            'rental_id' => $ioniqRental->id,
            'current_battery_percent' => 60,
            'charge_needed_kwh' => 24,
            'charging_cost' => 60000,
            'payment_proof' => 'proofs/sample-charging.jpg',
        ]);

        // 2) Sewa harian Vario di S001 selama 3 hari
        $varioRental = Rental::create([
            'parent_id' => null,
            'status' => RentalStatus::New,
            'user_id' => $bambang->id,
            'vehicle_id' => $vario->id,
            'garage_id' => $garageS001->id,
            'rental_type' => RentalType::Harian,
            'duration_days' => 3,
            'start_date' => $today,
            'end_date' => $today->addDays(3 - 1),
            'rent_cost' => 3 * 5000,          // 15.000
            'payment_proof' => 'proofs/sample-rental.jpg',
        ]);

        // 3) Perpanjangan 3 hari oleh admin
        Rental::create([
            'parent_id' => $varioRental->id,
            'status' => RentalStatus::Extend,
            'user_id' => $bambang->id,
            'vehicle_id' => $vario->id,
            'garage_id' => $garageS001->id,
            'rental_type' => RentalType::Harian,
            'duration_days' => 3,
            'start_date' => $varioRental->end_date->addDay(),
            'end_date' => $varioRental->end_date->addDays(3),
            'rent_cost' => 3 * 5000,          // 15.000
            'payment_proof' => 'proofs/sample-extend.jpg',
        ]);

        // Garasi yang dipakai berubah jadi used
        $garageE001->forceFill(['status' => GarageStatus::Used])->save();
        $garageS001->forceFill(['status' => GarageStatus::Used])->save();
    }
}
