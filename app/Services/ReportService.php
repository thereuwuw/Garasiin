<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Enums\RentalStatus;
use App\Models\Rental;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Per bulan dalam satu tahun (selalu 12 baris).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function monthly(int $year): Collection
    {
        $rows = self::base()
            ->selectRaw('MONTH(rentals.start_date) as period')
            ->whereYear('rentals.start_date', $year)
            ->groupByRaw('MONTH(rentals.start_date)')
            ->get()
            ->keyBy(fn (object $row) => (int) $row->period);

        return collect(range(1, 12))
            ->map(fn (int $month) => self::row($month, $rows->get($month)));
    }

    /**
     * Per hari dalam satu bulan (sebanyak jumlah hari bulan itu).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function daily(int $year, int $month): Collection
    {
        $rows = self::base()
            ->selectRaw('DAY(rentals.start_date) as period')
            ->whereYear('rentals.start_date', $year)
            ->whereMonth('rentals.start_date', $month)
            ->groupByRaw('DAY(rentals.start_date)')
            ->get()
            ->keyBy(fn (object $row) => (int) $row->period);

        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        return collect(range(1, $daysInMonth))
            ->map(fn (int $day) => self::row($day, $rows->get($day)));
    }

    /**
     * Per tipe garasi, dalam satu tahun atau satu bulan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function byGarageType(int $year, ?int $month = null): Collection
    {
        $rows = self::filtered(self::base(), $year, $month)
            ->join('garages', 'garages.id', '=', 'rentals.garage_id')
            ->addSelect('garages.type as period')
            ->groupBy('garages.type')
            ->get()
            ->keyBy('period');

        return collect(GarageType::cases())
            ->map(fn (GarageType $type) => self::row($type->value, $rows->get($type->value)));
    }

    /**
     * Per penyewa, dalam satu tahun atau satu bulan, pendapatan terbesar dulu.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function byTenant(int $year, ?int $month = null): Collection
    {
        return self::filtered(self::base(), $year, $month)
            ->join('users', 'users.id', '=', 'rentals.user_id')
            ->addSelect('users.name', 'users.email')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_income')
            ->get()
            ->map(fn (object $row) => self::row($row->name, $row) + ['email' => $row->email]);
    }

    private static function base(): QueryBuilder
    {
        return DB::table('rentals')
            ->leftJoin('chargings', 'chargings.rental_id', '=', 'rentals.id')
            ->selectRaw('SUM(CASE WHEN rentals.status = ? THEN 1 ELSE 0 END) as total_rentals', [RentalStatus::New->value])
            ->selectRaw('SUM(CASE WHEN rentals.status = ? THEN 1 ELSE 0 END) as total_extensions', [RentalStatus::Extend->value])
            ->selectRaw('COALESCE(SUM(rentals.rent_cost), 0) as rent_income')
            ->selectRaw('COALESCE(SUM(chargings.charging_cost), 0) as charging_income')
            ->selectRaw('COALESCE(SUM(rentals.rent_cost), 0) + COALESCE(SUM(chargings.charging_cost), 0) as total_income');
    }

    private static function filtered(QueryBuilder $query, int $year, ?int $month): QueryBuilder
    {
        return $query
            ->whereYear('rentals.start_date', $year)
            ->when($month !== null, fn (QueryBuilder $q) => $q->whereMonth('rentals.start_date', $month));
    }

    /**
     * @return array{period: int|string, total_rentals: int, total_extensions: int, rent_income: float, charging_income: float, total_income: float}
     */
    private static function row(int|string $period, ?object $data): array
    {
        return [
            'period' => $period,
            'total_rentals' => (int) ($data->total_rentals ?? 0),
            'total_extensions' => (int) ($data->total_extensions ?? 0),
            'rent_income' => (float) ($data->rent_income ?? 0),
            'charging_income' => (float) ($data->charging_income ?? 0),
            'total_income' => (float) ($data->total_income ?? 0),
        ];
    }

    /**
     * Pilihan tahun untuk filter laporan: dari tahun depan sampai tahun sewa pertama.
     *
     * @return list<int>
     */
    public static function years(): array
    {
        $first = Rental::query()->min('start_date');
        $firstYear = $first !== null ? Carbon::parse($first)->year : now()->year;

        return range(now()->year + 1, min($firstYear, now()->year));
    }
}
