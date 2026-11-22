<?php

namespace App\Services;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\RentalStatus;
use App\Enums\RentalType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VehicleType;
use App\Exceptions\GeneralException;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RentalService
{
    /**
     * Periode sewa baru. Tanggal mulai selalu hari ini.
     * - Harian: 1 sampai (jumlah hari bulan ini - 1) hari.
     * - Bulanan: sebanyak jumlah hari bulan ini (contoh mulai 20 Maret, 31 hari, selesai 19 April) dan "end_date = start_date + jumlah hari − 1"
     *
     * @return array{start: Carbon, end: Carbon, days: int}
     *
     * @throws GeneralException
     */
    public static function period(RentalType $rentalType, mixed $days = null, mixed $startDate = null): array
    {
        $start = filled($startDate) ? Carbon::parse($startDate)->startOfDay() : today();

        if ($start->lt(today())) {
            throw new GeneralException('Tanggal mulai tidak boleh sebelum hari ini.');
        }

        $daysInMonth = $start->daysInMonth;

        if ($rentalType === RentalType::Harian) {
            if (! is_numeric($days)) {
                throw new GeneralException('Jumlah hari wajib diisi untuk sewa harian.');
            }

            $days = (int) $days;
            $maximumDays = $daysInMonth - 1;

            if ($days < 1 || $days > $maximumDays) {
                throw new GeneralException("Jumlah hari harus antara 1 dan {$maximumDays} untuk bulan ini.");
            }
        } else {
            $days = $daysInMonth;
        }

        return [
            'start' => $start,
            'end' => $start->copy()->addDays($days - 1),
            'days' => $days,
        ];
    }

    /**
     * Hitung tanggal dan biaya sewa baru tanpa menyimpan apa pun, untuk ditampilkan
     * ke penyewa sebelum submit.
     *
     * Keys: garage_id, rental_type, days, vehicle_type, max_battery_capacity_kwh, current_battery_percent.
     *
     * @param  array<string, mixed>  $data
     * @return array{start_date: string, end_date: string, days: int, daily_price: float, rent_cost: float, charge_needed_kwh: float, charging_cost: float, total_cost: float}
     *
     * @throws GeneralException
     */
    public static function preview(array $data): array
    {
        $garage = Garage::query()->find($data['garage_id'] ?? null);

        if ($garage === null) {
            throw new GeneralException('Garasi tidak ditemukan.');
        }

        $vehicleType = VehicleType::tryFrom((string) ($data['vehicle_type'] ?? ''));

        if ($vehicleType !== null && ! in_array($garage->type, $vehicleType->allowedGarageTypes(), true)) {
            throw new GeneralException('Kendaraan ini tidak bisa diparkir di tipe garasi tersebut.');
        }

        $rentalType = self::toRentalType($data['rental_type'] ?? null);
        $period = self::period($rentalType, $data['days'] ?? null, $data['start_date'] ?? null);
        $rentCost = RentalCostService::rentCost($garage, $rentalType, $period['days']);

        $charging = ['kwh' => 0.0, 'cost' => 0.0];

        if (
            self::needsCharging($vehicleType, $garage)
            && is_numeric($data['max_battery_capacity_kwh'] ?? null)
            && is_numeric($data['current_battery_percent'] ?? null)
        ) {
            $charging = self::chargingTerms(
                (float) $data['max_battery_capacity_kwh'],
                $data['current_battery_percent'],
                $garage,
            );
        }

        return [
            'start_date' => $period['start']->toDateString(),
            'end_date' => $period['end']->toDateString(),
            'days' => $period['days'],
            'daily_price' => (float) $garage->daily_price,
            'rent_cost' => $rentCost,
            'charge_needed_kwh' => $charging['kwh'],
            'charging_cost' => $charging['cost'],
            'total_cost' => round($rentCost + $charging['cost'], 2),
        ];
    }

    /**
     * Simpan sewa baru (status new) beserta charging kalau perlu, lalu garasi menjadi used.
     *
     * Keys: vehicle (array: type, plate_number, brand, model, max_battery_capacity_kwh),
     * garage_id, rental_type, days, current_battery_percent,
     * payment_proof_path, charging_payment_proof_path.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws GeneralException
     */
    public static function create(User $user, array $data): Rental
    {
        if ($user->role !== UserRole::Penyewa || $user->status !== UserStatus::Accepted) {
            throw new GeneralException('Akun Anda belum disetujui admin.');
        }

        if (empty($data['payment_proof_path'])) {
            throw new GeneralException('Bukti pelunasan biaya sewa wajib diupload.');
        }

        return DB::transaction(function () use ($user, $data): Rental {
            // Kunci garasi supaya dua penyewa tidak bisa memesan garasi yang sama bersamaan.
            $garage = Garage::query()->lockForUpdate()->find($data['garage_id'] ?? null);

            if ($garage === null) {
                throw new GeneralException('Garasi tidak ditemukan.');
            }

            $vehicle = VehicleService::resolveForUser($user, (array) ($data['vehicle'] ?? []));

            if (! in_array($garage->type, $vehicle->type->allowedGarageTypes(), true)) {
                throw new GeneralException('Kendaraan ini tidak bisa diparkir di tipe garasi tersebut.');
            }

            $rentalType = self::toRentalType($data['rental_type'] ?? null);
            $period = self::period($rentalType, $data['days'] ?? null, $data['start_date'] ?? null);

            if (self::garageIsBooked($garage->id, $period['start'], $period['end'])) {
                throw new GeneralException('Garasi sudah disewa pada tanggal tersebut. Silakan pilih garasi lain.');
            }

            if (self::vehicleIsRented($vehicle->id, $period['start'], $period['end'])) {
                throw new GeneralException('Kendaraan ini masih tercatat di sewa lain pada tanggal tersebut.');
            }

            $charging = null;

            if (self::needsCharging($vehicle->type, $garage)) {
                $charging = self::chargingTerms(
                    (float) $vehicle->max_battery_capacity_kwh,
                    $data['current_battery_percent'] ?? null,
                    $garage,
                );

                if ($charging['cost'] > 0 && empty($data['charging_payment_proof_path'])) {
                    throw new GeneralException('Bukti pembayaran isi listrik wajib diupload.');
                }
            }

            $rental = Rental::create([
                'parent_id' => null,
                'status' => RentalStatus::New,
                'user_id' => $user->id,
                'vehicle_id' => $vehicle->id,
                'garage_id' => $garage->id,
                'rental_type' => $rentalType,
                'duration_days' => $period['days'],
                'start_date' => $period['start']->toDateString(),
                'end_date' => $period['end']->toDateString(),
                'rent_cost' => RentalCostService::rentCost($garage, $rentalType, $period['days']),
                'payment_proof' => $data['payment_proof_path'],
            ]);

            // Baterai 100% berarti tidak ada yang diisi, jadi tidak ada data charging.
            if ($charging !== null && $charging['cost'] > 0) {
                $rental->charging()->create([
                    'current_battery_percent' => $charging['percent'],
                    'charge_needed_kwh' => $charging['kwh'],
                    'charging_cost' => $charging['cost'],
                    'payment_proof' => $data['charging_payment_proof_path'],
                ]);
            }

            if ($period['start']->isToday()) {
                $garage->forceFill(['status' => GarageStatus::Used])->save();
            }

            return $rental->load(['garage', 'vehicle', 'charging']);
        });
    }

    /**
     * Sewa milik penyewa (baris new) beserta perpanjangan dan charging-nya, terbaru dulu.
     *
     * @return Collection<int, Rental>
     */
    public static function tenantRentals(User $user): Collection
    {
        return Rental::query()
            ->where('user_id', $user->id)
            ->where('status', RentalStatus::New)
            ->with(['garage', 'vehicle', 'charging', 'extensions'])
            ->latest('start_date')
            ->get();
    }

    /**
     * Daftar sewa yang sedang berjalan hari ini, untuk halaman perpanjangan admin.
     * Satu entri per sewa (baris new), diurutkan berdasarkan kode garasi.
     * Sewa yang selesai hari ini masih ditampilkan, supaya admin masih bisa memperpanjangnya
     * di hari terakhir.
     *
     * @return Collection<int, Rental>
     */
    public static function activeRentals(): Collection
    {
        $today = today()->toDateString();

        $coversToday = fn (Builder $query) => $query
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today);

        return Rental::query()
            ->where('status', RentalStatus::New)
            ->where(fn (Builder $query) => $query
                ->where($coversToday)
                ->orWhereHas('extensions', $coversToday))
            ->with(['garage', 'vehicle', 'user', 'extensions'])
            ->get()
            ->sortBy(fn (Rental $rental) => $rental->garage->code)
            ->values();
    }

    /**
     * Hitung perpanjangan tanpa menyimpan, untuk ditampilkan ke admin sebelum upload bukti bayar.
     *
     * @return array{current_end_date: string, start_date: string, end_date: string, extra_days: int, daily_price: float, rent_cost: float}
     *
     * @throws GeneralException
     */
    public static function previewExtension(Rental $rental, string|CarbonInterface $newEndDate): array
    {
        $terms = self::extensionTerms($rental, $newEndDate);

        return [
            'current_end_date' => $terms['start']->copy()->subDay()->toDateString(),
            'start_date' => $terms['start']->toDateString(),
            'end_date' => $terms['end']->toDateString(),
            'extra_days' => $terms['extra_days'],
            'daily_price' => $terms['daily_price'],
            'rent_cost' => $terms['rent_cost'],
        ];
    }

    /**
     * Perpanjang sewa (khusus admin), tanpa batas maksimal. Hasilnya baris baru dengan status extend
     * dan parent_id = id sewa awal. Data input penyewa disalin, tidak diubah.
     * Biaya selalu dihitung harian: hari tambahan x harga harian garasi.
     *
     * @throws GeneralException
     */
    public static function extend(Rental $rental, string|CarbonInterface $newEndDate, string $paymentProofPath): Rental
    {
        if ($paymentProofPath === '') {
            throw new GeneralException('Bukti pembayaran perpanjangan wajib diupload.');
        }

        return DB::transaction(function () use ($rental, $newEndDate, $paymentProofPath): Rental {
            // Kunci garasi yang sama seperti create(), supaya perpanjangan dan sewa baru tidak bentrok.
            Garage::query()->lockForUpdate()->find($rental->garage_id);

            $terms = self::extensionTerms($rental, $newEndDate);
            $original = $terms['original'];

            return Rental::create([
                'parent_id' => $original->id,
                'status' => RentalStatus::Extend,
                'user_id' => $original->user_id,
                'vehicle_id' => $original->vehicle_id,
                'garage_id' => $original->garage_id,
                'rental_type' => RentalType::Harian,
                'duration_days' => $terms['extra_days'],
                'start_date' => $terms['start']->toDateString(),
                'end_date' => $terms['end']->toDateString(),
                'rent_cost' => $terms['rent_cost'],
                'payment_proof' => $paymentProofPath,
            ]);
        });
    }

    /**
     * Baris sewa awal (status new). Kalau yang diberikan baris extend, kembalikan parent-nya.
     *
     * @throws GeneralException
     */
    public static function originalOf(Rental $rental): Rental
    {
        if ($rental->status !== RentalStatus::Extend) {
            return $rental;
        }

        $parent = $rental->parent;

        if ($parent === null) {
            throw new GeneralException('Data sewa awal tidak ditemukan.');
        }

        return $parent;
    }

    /**
     * Tanggal selesai terakhir sebuah sewa, termasuk semua perpanjangannya.
     *
     * @throws GeneralException
     */
    public static function latestEndDate(Rental $rental): Carbon
    {
        $original = self::originalOf($rental);

        $latestExtension = $original->relationLoaded('extensions')
            ? $original->extensions->max('end_date')
            : $original->extensions()->max('end_date');

        return $latestExtension !== null
            ? Carbon::parse($latestExtension)->startOfDay()
            : $original->end_date->copy()->startOfDay();
    }

    /**
     * Aturan perpanjangan yang dipakai bersama oleh extend() dan previewExtension().
     *
     * @return array{original: Rental, start: Carbon, end: Carbon, extra_days: int, daily_price: float, rent_cost: float}
     *
     * @throws GeneralException
     */
    private static function extensionTerms(Rental $rental, string|CarbonInterface $newEndDate): array
    {
        $original = self::originalOf($rental);
        $currentEnd = self::latestEndDate($original);
        $newEnd = Carbon::parse($newEndDate)->startOfDay();
        $extensionStart = $currentEnd->copy()->addDay();

        if ($currentEnd->lt(today())) {
            throw new GeneralException('Sewa ini sudah berakhir sehingga tidak bisa diperpanjang.');
        }

        if (! $newEnd->gt($currentEnd)) {
            throw new GeneralException('Tanggal selesai baru harus setelah '.$currentEnd->format('d-m-Y').'.');
        }

        if (self::garageIsBooked($original->garage_id, $extensionStart, $newEnd, $original->id)) {
            throw new GeneralException('Garasi sudah dipesan penyewa lain pada rentang tanggal tersebut.');
        }

        if (self::vehicleIsRented($original->vehicle_id, $extensionStart, $newEnd, $original->id)) {
            throw new GeneralException('Kendaraan ini tercatat di sewa lain pada rentang tanggal tersebut.');
        }

        $garage = $original->garage;
        $extraDays = (int) $currentEnd->diffInDays($newEnd);

        return [
            'original' => $original,
            'start' => $extensionStart,
            'end' => $newEnd,
            'extra_days' => $extraDays,
            'daily_price' => (float) $garage->daily_price,
            'rent_cost' => RentalCostService::extensionCost($garage, $extraDays),
        ];
    }

    /**
     * Ubah input form (string) menjadi enum RentalType.
     *
     * @throws GeneralException
     */
    private static function toRentalType(mixed $value): RentalType
    {
        if ($value instanceof RentalType) {
            return $value;
        }

        $rentalType = RentalType::tryFrom((string) $value);

        if ($rentalType === null) {
            throw new GeneralException('Tipe sewa tidak valid.');
        }

        return $rentalType;
    }

    private static function needsCharging(?VehicleType $vehicleType, Garage $garage): bool
    {
        return $vehicleType === VehicleType::MobilListrik && $garage->type === GarageType::MobilListrik;
    }

    /**
     * @return array{percent: float, kwh: float, cost: float}
     *
     * @throws GeneralException
     */
    private static function chargingTerms(float $capacityKwh, mixed $currentPercent, Garage $garage): array
    {
        if (! is_numeric($currentPercent)) {
            throw new GeneralException('Persentase baterai saat ini wajib diisi.');
        }

        $percent = (float) $currentPercent;

        if ($percent < 0 || $percent > 100) {
            throw new GeneralException('Persentase baterai harus antara 0 dan 100.');
        }

        $kwh = RentalCostService::chargeNeededKwh($capacityKwh, $percent);

        return [
            'percent' => $percent,
            'kwh' => $kwh,
            'cost' => RentalCostService::chargingCost($garage, $kwh),
        ];
    }

    /**
     * Apakah garasi sudah dipakai pada periode [start, end).
     * $exceptChainId: id sewa awal yang dikecualikan (beserta perpanjangannya), dipakai saat memperpanjang.
     */
    private static function garageIsBooked(
        string $garageId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $exceptChainId = null,
    ): bool {
        return self::overlapping(Rental::query()->where('garage_id', $garageId), $start, $end, $exceptChainId)->exists();
    }

    /**
     * Apakah kendaraan sedang ada di garasi lain pada periode [start, end).
     */
    private static function vehicleIsRented(
        string $vehicleId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $exceptChainId = null,
    ): bool {
        return self::overlapping(Rental::query()->where('vehicle_id', $vehicleId), $start, $end, $exceptChainId)->exists();
    }

    /**
     * Baris sewa yang bertabrakan dengan [start, end). Sewa yang selesai tanggal X
     * tidak bentrok dengan sewa yang mulai tanggal X.
     *
     * @param  Builder<Rental>  $query
     * @return Builder<Rental>
     */
    private static function overlapping(
        Builder $query,
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $exceptChainId,
    ): Builder {
        return $query
            ->when($exceptChainId !== null, fn (Builder $q) => $q
                ->where('id', '!=', $exceptChainId)
                ->where(fn (Builder $q) => $q
                    ->whereNull('parent_id')
                    ->orWhere('parent_id', '!=', $exceptChainId)))
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString());
    }
}
