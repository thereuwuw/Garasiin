<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GarageType;
use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Services\GarageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GarageController extends Controller
{
    public function index(Request $request): View
    {
        // Pastikan status used/available sesuai hari ini, walaupun scheduler belum jalan.
        GarageService::syncStatuses();

        $type = GarageType::tryFrom((string) $request->query('type'));

        return view('admin.garages.index', [
            'garages' => Garage::query()
                ->when($type !== null, fn ($q) => $q->where('type', $type))
                ->orderBy('type')
                ->orderBy('sequence')
                ->paginate(50)
                ->withQueryString(),
            'counts' => Garage::query()->selectRaw('type, COUNT(*) as total')->groupBy('type')->pluck('total', 'type'),
            'types' => GarageType::cases(),
            'selectedType' => $type,
        ]);
    }

    public function create(): View
    {
        $counts = Garage::query()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return view('admin.garages.create', [
            'types' => GarageType::cases(),
            'maxPerType' => GarageService::MAX_PER_TYPE,
            // contoh: ['sepeda' => 994, 'mobil' => 994, 'mobil_listrik' => 996]
            'remaining' => collect(GarageType::cases())->mapWithKeys(fn (GarageType $type) => [
                $type->value => GarageService::MAX_PER_TYPE - (int) ($counts[$type->value] ?? 0),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(GarageType::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.GarageService::MAX_PER_TYPE],
            'daily_price' => ['required', 'numeric', 'min:1'],
            'electricity_price_per_kwh' => ['exclude_unless:type,mobil_listrik', 'required', 'numeric', 'min:1'],
        ]);

        try {
            $garages = GarageService::createBatch($validated);
        } catch (GeneralException $e) {
            return back()->withInput()->withErrors(['garage' => $e->getMessage()]);
        }

        $range = $garages->count() === 1
            ? $garages->first()->code
            : $garages->first()->code.' - '.$garages->last()->code;

        return redirect()->route('admin.garages.index')
            ->with('success', "{$garages->count()} garasi berhasil dibuat ({$range}).");
    }
}
