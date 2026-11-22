<?php

namespace App\Services;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Exceptions\GeneralException;
use App\Models\Garage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GarageService
{
    public const MAX_PER_TYPE = 999;

    /**
     * Peta tipe kendaraan ke tipe garasi dalam bentuk string, untuk dikirim ke view/JavaScript.
     *
     * @return array<string, list<string>>
     */
    public static function allowedMap(): array
    {
        $map = [];

        foreach (VehicleType::cases() as $vehicleType) {
            $map[$vehicleType->value] = array_map(
                fn (GarageType $garageType) => $garageType->value,
                $vehicleType->allowedGarageTypes(),
            );
        }

        return $map;
    }

    /**
     * Admin membuat banyak garasi sekaligus. Kode lanjut dari nomor terakhir tipe yang sama,
     * misalnya sudah ada S001-S005, maka 3 garasi baru menjadi S006-S008.
     *
     * Keys: type, quantity, daily_price, electricity_price_per_kwh (wajib untuk mobil_listrik).
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, Garage>
     *
     * @throws GeneralException
     */
    public static function createBatch(array $data): Collection
    {
        $type = $data['type'] instanceof GarageType
            ? $data['type']
            : GarageType::tryFrom((string) ($data['type'] ?? ''));

        $quantity = (int) ($data['quantity'] ?? 0);
        $dailyPrice = (float) ($data['daily_price'] ?? 0);
        $electricityPrice = $data['electricity_price_per_kwh'] ?? null;

        if ($type === null) {
            throw new GeneralException('Tipe garasi tidak valid.');
        }

        if ($quantity < 1) {
            throw new GeneralException('Jumlah garasi minimal 1.');
        }

        if ($dailyPrice <= 0) {
            throw new GeneralException('Harga sewa harian harus lebih dari 0.');
        }

        if ($type === GarageType::MobilListrik) {
            if (! is_numeric($electricityPrice) || (float) $electricityPrice <= 0) {
                throw new GeneralException('Harga isi listrik per kWh wajib diisi untuk garasi mobil listrik.');
            }
            $electricityPrice = (float) $electricityPrice;
        } else {
            $electricityPrice = null;
        }

        return DB::transaction(function () use ($type, $quantity, $dailyPrice, $electricityPrice): Collection {
            // Kunci baris tipe ini supaya dua admin tidak membuat nomor yang sama.
            $last = (int) Garage::query()->where('type', $type)->lockForUpdate()->max('sequence');
            $remaining = self::MAX_PER_TYPE - $last;

            if ($quantity > $remaining) {
                throw new GeneralException("Sisa kuota {$type->label()} tinggal {$remaining}.");
            }

            $garages = new Collection;

            for ($i = 1; $i <= $quantity; $i++) {
                $sequence = $last + $i;

                $garages->push(Garage::create([
                    'code' => $type->prefix().str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                    'type' => $type,
                    'sequence' => $sequence,
                    'daily_price' => $dailyPrice,
                    'electricity_price_per_kwh' => $electricityPrice,
                ]));
            }

            return $garages;
        });
    }

    /**
     * Garasi suatu tipe yang tidak sedang disewa pada periode [start, end).
     *
     * @return Collection<int, Garage>
     */
    public static function availableFor(GarageType $garageType, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return Garage::query()
            ->where('type', $garageType)
            ->whereDoesntHave('rentals', fn (Builder $query) => $query
                ->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString()))
            ->orderBy('sequence')
            ->get();
    }

    /**
     * Sesuaikan kolom status semua garasi dengan sewa hari ini: used kalau ada sewa (baru atau
     * perpanjangan) yang berjalan hari ini, available kalau tidak ada.
     */
    public static function syncStatuses(): void
    {
        $today = today()->toDateString();

        $occupiedToday = fn (Builder $query) => $query
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today);

        DB::transaction(function () use ($occupiedToday): void {
            Garage::query()
                ->whereHas('rentals', $occupiedToday)
                ->where('status', '!=', GarageStatus::Used->value)
                ->update(['status' => GarageStatus::Used->value]);

            Garage::query()
                ->whereDoesntHave('rentals', $occupiedToday)
                ->where('status', '!=', GarageStatus::Available->value)
                ->update(['status' => GarageStatus::Available->value]);
        });
    }
}
