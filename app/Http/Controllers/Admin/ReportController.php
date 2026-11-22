<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Jenis laporan: per bulan dalam tahun tertentu, per hari dalam bulan tertentu,
     * per tipe garasi, dan per penyewa.
     */
    public const TYPES = [
        'bulanan' => 'Per Bulan',
        'harian' => 'Per Hari',
        'tipe' => 'Per Tipe Garasi',
        'penyewa' => 'Per Penyewa',
    ];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(self::TYPES))],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $type = $validated['type'] ?? 'bulanan';
        $year = (int) ($validated['year'] ?? now()->year);
        $month = isset($validated['month']) ? (int) $validated['month'] : null;

        // Laporan harian wajib punya bulan, default bulan ini.
        if ($type === 'harian' && $month === null) {
            $month = now()->month;
        }

        // Laporan bulanan selalu satu tahun penuh, jadi bulan diabaikan.
        if ($type === 'bulanan') {
            $month = null;
        }

        $rows = match ($type) {
            'bulanan' => ReportService::monthly($year),
            'harian' => ReportService::daily($year, $month),
            'tipe' => ReportService::byGarageType($year, $month),
            'penyewa' => ReportService::byTenant($year, $month),
        };

        return view('admin.reports.index', [
            'types' => self::TYPES,
            'type' => $type,
            'year' => $year,
            'month' => $month,
            'years' => ReportService::years(),
            'rows' => $rows,
            'totals' => [
                'total_rentals' => $rows->sum('total_rentals'),
                'total_extensions' => $rows->sum('total_extensions'),
                'rent_income' => (float) $rows->sum('rent_income'),
                'charging_income' => (float) $rows->sum('charging_income'),
                'total_income' => (float) $rows->sum('total_income'),
            ],
        ]);
    }
}
