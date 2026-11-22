<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Services\GarageService;
use App\Services\RentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RentalController extends Controller
{
    public function index(Request $request): View
    {
        return view('tenant.rentals.index', [
            'rentals' => RentalService::tenantRentals($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        $today = today();

        return view('tenant.rentals.create', [
            'vehicles' => $request->user()->vehicles()->orderBy('brand')->get(),
            'vehicleTypes' => VehicleType::cases(),
            'garageTypesByVehicle' => GarageService::allowedMap(),
            'startDate' => $today->toDateString(),
            'maxDailyDays' => $today->daysInMonth - 1,
        ]);
    }

    /**
     * tipe garasi yang boleh untuk tipe kendaraan, dan daftar garasi tipe tersebut
     * yang tersedia untuk periode sewa.
     */
    public function availableGarages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'garage_type' => ['nullable', Rule::enum(GarageType::class)],
            'rental_type' => ['nullable', Rule::enum(RentalType::class)],
            'days' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'start_date.after_or_equal' => 'Tanggal mulai tidak boleh sebelum hari ini.',
        ]);

        $vehicleType = VehicleType::from($validated['vehicle_type']);
        $allowedTypes = $vehicleType->allowedGarageTypes();
        $allowedValues = array_map(fn (GarageType $type) => $type->value, $allowedTypes);
        $garageType = GarageType::tryFrom($validated['garage_type'] ?? '');

        if ($garageType === null || ! in_array($garageType, $allowedTypes, true) || empty($validated['rental_type'])) {
            return response()->json(['allowed_types' => $allowedValues, 'garages' => []]);
        }

        try {
            $period = RentalService::period(
                RentalType::from($validated['rental_type']),
                $validated['days'] ?? null,
                $validated['start_date'] ?? null,
            );
        } catch (GeneralException $e) {
            return response()->json(['allowed_types' => $allowedValues, 'garages' => [], 'message' => $e->getMessage()], 422);
        }

        $garages = GarageService::availableFor($garageType, $period['start'], $period['end'])
            ->map(fn (Garage $garage) => [
                'id' => $garage->id,
                'code' => $garage->code,
                'type' => $garage->type->value,
                'type_label' => $garage->type->label(),
                'daily_price' => (float) $garage->daily_price,
                'electricity_price_per_kwh' => $garage->electricity_price_per_kwh !== null
                    ? (float) $garage->electricity_price_per_kwh
                    : null,
            ])
            ->values();

        return response()->json([
            'allowed_types' => $allowedValues,
            'end_date' => $period['end']->toDateString(),
            'garages' => $garages,
        ]);
    }

    /**
     * total biaya ditampilkan sebelum penyewa submit.
     */
    public function preview(Request $request): JsonResponse
    {
        try {
            return response()->json(RentalService::preview($request->only([
                'garage_id',
                'rental_type',
                'start_date',
                'days',
                'vehicle_type',
                'max_battery_capacity_kwh',
                'current_battery_percent',
            ])));
        } catch (GeneralException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'plate_number' => ['exclude_if:vehicle_type,sepeda', 'required', 'string', 'max:15'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'max_battery_capacity_kwh' => ['exclude_unless:vehicle_type,mobil_listrik', 'required', 'numeric', 'min:1'],
            'rental_type' => ['required', Rule::enum(RentalType::class)],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'days' => ['exclude_unless:rental_type,harian', 'required', 'integer', 'min:1'],
            'garage_id' => ['required', 'uuid', 'exists:garages,id'],
            'current_battery_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_proof' => ['required', 'image', 'max:2048'],
            'charging_payment_proof' => ['nullable', 'image', 'max:2048'],
        ]);

        $paths = [
            'payment_proof_path' => $request->file('payment_proof')->store('proofs', 'public'),
            'charging_payment_proof_path' => $request->file('charging_payment_proof')?->store('proofs', 'public'),
        ];

        try {
            RentalService::create($request->user(), [
                'vehicle' => [
                    'type' => $validated['vehicle_type'],
                    'plate_number' => $validated['plate_number'] ?? null,
                    'brand' => $validated['brand'],
                    'model' => $validated['model'],
                    'max_battery_capacity_kwh' => $validated['max_battery_capacity_kwh'] ?? null,
                ],
                'garage_id' => $validated['garage_id'],
                'rental_type' => $validated['rental_type'],
                'start_date' => $validated['start_date'],
                'days' => $validated['days'] ?? null,
                'current_battery_percent' => $validated['current_battery_percent'] ?? null,
                ...$paths,
            ]);
        } catch (GeneralException $e) {
            // Sewa gagal disimpan, jadi file yang sudah diupload dihapus lagi.
            Storage::disk('public')->delete(array_filter($paths));

            return back()->withInput()->withErrors(['rental' => $e->getMessage()]);
        }

        return redirect()->route('tenant.rentals.index')
            ->with('success', 'Sewa garasi berhasil disimpan.');
    }
}
