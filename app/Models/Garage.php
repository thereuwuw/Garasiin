<?php

namespace App\Models;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'type', 'sequence', 'daily_price', 'electricity_price_per_kwh'])]
class Garage extends Model
{
    use HasUuids;

    protected $attributes = [
        'status' => 'available',
    ];

    protected function casts(): array
    {
        return [
            'type' => GarageType::class,
            'status' => GarageStatus::class,
            'sequence' => 'integer',
            'daily_price' => 'decimal:2',
            'electricity_price_per_kwh' => 'decimal:2',
        ];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
