<?php

namespace App\Models;

use App\Enums\RentalStatus;
use App\Enums\RentalType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'parent_id', 'status', 'user_id', 'vehicle_id', 'garage_id', 'rental_type',
    'duration_days', 'start_date', 'end_date', 'rent_cost', 'payment_proof',
])]
class Rental extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'status' => RentalStatus::class,
            'rental_type' => RentalType::class,
            'duration_days' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_cost' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    // Sewa awal (untuk baris extend)
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Rental::class, 'parent_id');
    }

    // Daftar extend dari sewa ini
    public function extensions(): HasMany
    {
        return $this->hasMany(Rental::class, 'parent_id');
    }

    public function charging(): HasOne
    {
        return $this->hasOne(Charging::class);
    }
}
