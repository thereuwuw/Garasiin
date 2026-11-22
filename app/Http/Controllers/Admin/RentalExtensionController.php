<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\RentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RentalExtensionController extends Controller
{
    /**
     * Daftar garasi yang sedang disewa hari ini.
     */
    public function index(): View
    {
        $rentals = RentalService::activeRentals();

        return view('admin.extensions.index', [
            'rentals' => $rentals,
            'currentEndDates' => $rentals->mapWithKeys(fn (Rental $rental) => [
                $rental->id => RentalService::latestEndDate($rental),
            ]),
        ]);
    }

    /**
     * Form perpanjangan: admin memilih tanggal selesai baru dan upload bukti bayar.
     */
    public function edit(Rental $rental): View|RedirectResponse
    {
        try {
            $original = RentalService::originalOf($rental)->load(['garage', 'vehicle', 'user', 'charging', 'extensions']);
            $currentEnd = RentalService::latestEndDate($original);
        } catch (GeneralException $e) {
            return redirect()->route('admin.extensions.index')->withErrors(['extension' => $e->getMessage()]);
        }

        return view('admin.extensions.edit', [
            'rental' => $original,
            'currentEnd' => $currentEnd,
            'minNewEnd' => $currentEnd->copy()->addDay()->toDateString(),
        ]);
    }

    /**
     * AJAX: sisa biaya yang harus dibayar untuk tanggal selesai baru.
     */
    public function preview(Request $request, Rental $rental): JsonResponse
    {
        $validated = $request->validate([
            'new_end_date' => ['required', 'date'],
        ]);

        try {
            return response()->json(RentalService::previewExtension($rental, $validated['new_end_date']));
        } catch (GeneralException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'new_end_date' => ['required', 'date'],
            'payment_proof' => ['required', 'image', 'max:2048'],
        ]);

        $path = $request->file('payment_proof')->store('proofs', 'public');

        try {
            $extension = RentalService::extend($rental, $validated['new_end_date'], $path);
        } catch (GeneralException $e) {
            Storage::disk('public')->delete($path);

            return back()->withInput()->withErrors(['extension' => $e->getMessage()]);
        }

        return redirect()->route('admin.extensions.index')->with(
            'success',
            "Sewa diperpanjang {$extension->duration_days} hari sampai ".$extension->end_date->format('d-m-Y').'.'
        );
    }
}
