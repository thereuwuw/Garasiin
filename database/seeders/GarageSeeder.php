<?php

namespace Database\Seeders;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Models\Garage;
use Illuminate\Database\Seeder;

class GarageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            ['prefix' => 'S', 'type' => GarageType::Sepeda,       'count' => 5, 'daily_price' => 5000,  'electricity' => null],
            ['prefix' => 'C', 'type' => GarageType::Mobil,        'count' => 5, 'daily_price' => 30000, 'electricity' => null],
            ['prefix' => 'E', 'type' => GarageType::MobilListrik, 'count' => 3, 'daily_price' => 50000, 'electricity' => 2500],
        ];

        foreach ($groups as $group) {
            for ($sequence = 1; $sequence <= $group['count']; $sequence++) {
                Garage::forceCreate([
                    'code' => $group['prefix'] . str_pad($sequence, 3, '0', STR_PAD_LEFT), // S001, C001, E001
                    'type' => $group['type'],
                    'sequence' => $sequence,
                    'daily_price' => $group['daily_price'],
                    'electricity_price_per_kwh' => $group['electricity'],
                    'status' => GarageStatus::Available,
                ]);
            }
        }
    }
}
