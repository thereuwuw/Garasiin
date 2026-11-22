<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rental_id', 'current_battery_percent', 'charge_needed_kwh', 'charging_cost', 'payment_proof'])]
class Charging extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'current_battery_percent' => 'decimal:2',
            'charge_needed_kwh' => 'decimal:2',
            'charging_cost' => 'decimal:2',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
