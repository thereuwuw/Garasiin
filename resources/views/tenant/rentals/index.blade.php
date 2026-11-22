@extends('layouts.app')
 
@section('title', 'Sewa Saya')
 
@php
    $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
@endphp
 
@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-sm font-medium text-blue-600">Penyewa</p>
        <h1 class="text-3xl font-bold text-slate-900">Sewa Saya</h1>
        <p class="mt-1 text-sm text-slate-500">Semua garasi yang pernah Anda sewa, dari yang terbaru.</p>
    </div>
    <a href="{{ route('tenant.rentals.create') }}"
       class="rounded-full bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700">
        + Sewa Baru
    </a>
</div>
 
@forelse ($rentals as $rental)
    @php
        // Tanggal selesai terkini, termasuk perpanjangan dari admin
        $latestEnd = \App\Services\RentalService::latestEndDate($rental);
        $today = today();
 
        if ($rental->start_date->gt($today)) {
            [$statusLabel, $statusClass] = ['Akan datang', 'bg-amber-50 text-amber-700'];
        } elseif ($latestEnd->gte($today)) {
            [$statusLabel, $statusClass] = ['Berjalan', 'bg-emerald-50 text-emerald-700'];
        } else {
            [$statusLabel, $statusClass] = ['Selesai', 'bg-slate-100 text-slate-600'];
        }
 
        $extensionCost = $rental->extensions->sum('rent_cost');
        $chargingCost = (float) ($rental->charging?->charging_cost ?? 0);
        $total = (float) $rental->rent_cost + $extensionCost + $chargingCost;
        $vehicle = $rental->vehicle;
    @endphp
 
    <div class="mb-5 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-6 p-6 md:grid-cols-[auto_1fr_auto] md:items-center">
            {{-- Kode garasi --}}
            <div class="flex h-20 w-20 flex-col items-center justify-center rounded-2xl bg-blue-950 text-white">
                <span class="text-xl font-bold">{{ $rental->garage->code }}</span>
                <span class="text-[10px] uppercase tracking-wider text-blue-300">Garasi</span>
            </div>
 
            {{-- Detail --}}
            <div class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                <div>
                    <p class="text-slate-500">Tipe garasi</p>
                    <p class="font-semibold text-slate-900">{{ $rental->garage->type->label() }}</p>
                </div>
                <div>
                    <p class="text-slate-500">Kendaraan</p>
                    <p class="font-semibold text-slate-900">
                        {{ $vehicle->plate_number ?? 'Tanpa plat' }} · {{ $vehicle->brand }} {{ $vehicle->model }}
                    </p>
                </div>
                <div>
                    <p class="text-slate-500">Tipe sewa</p>
                    <p class="font-semibold text-slate-900">{{ $rental->rental_type->label() }}</p>
                </div>
                <div>
                    <p class="text-slate-500">Periode</p>
                    <p class="font-semibold text-slate-900">
                        {{ $rental->start_date->format('d-m-Y') }} s/d {{ $latestEnd->format('d-m-Y') }}
                    </p>
                </div>
            </div>
 
            <span class="justify-self-start rounded-full px-4 py-1.5 text-xs font-semibold md:justify-self-end {{ $statusClass }}">
                {{ $statusLabel }}
            </span>
        </div>
 
        {{-- Biaya --}}
        <div class="grid gap-4 border-t border-slate-100 bg-slate-50 px-6 py-4 text-sm sm:grid-cols-4">
            <div>
                <p class="text-slate-500">Biaya sewa</p>
                <p class="font-semibold text-slate-900">{{ $rupiah($rental->rent_cost) }}</p>
            </div>
            <div>
                <p class="text-slate-500">Perpanjangan</p>
                <p class="font-semibold text-slate-900">{{ $rupiah($extensionCost) }}</p>
            </div>
            <div>
                <p class="text-slate-500">Biaya listrik</p>
                <p class="font-semibold text-slate-900">
                    {{ $rupiah($chargingCost) }}
                    @if ($rental->charging)
                        <span class="font-normal text-slate-500">
                            ({{ number_format((float) $rental->charging->charge_needed_kwh, 2, ',', '.') }} kWh)
                        </span>
                    @endif
                </p>
            </div>
            <div>
                <p class="text-slate-500">Total</p>
                <p class="text-base font-bold text-blue-700">{{ $rupiah($total) }}</p>
            </div>
        </div>
 
        {{-- Riwayat perpanjangan --}}
        @if ($rental->extensions->isNotEmpty())
            <div class="border-t border-slate-100 px-6 py-4">
                <p class="mb-2 text-sm font-semibold text-slate-700">Riwayat perpanjangan</p>
                <ul class="space-y-1 text-sm text-slate-600">
                    @foreach ($rental->extensions->sortBy('start_date') as $extension)
                        <li class="flex flex-wrap justify-between gap-2">
                            <span>
                                {{ $extension->start_date->format('d-m-Y') }} s/d {{ $extension->end_date->format('d-m-Y') }}
                                ({{ $extension->duration_days }} hari)
                            </span>
                            <span class="font-medium text-slate-900">{{ $rupiah($extension->rent_cost) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@empty
    <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
        <p class="text-lg font-semibold text-slate-900">Belum ada sewa</p>
        <p class="mt-1 text-sm text-slate-500">Mulai sewa garasi pertama Anda.</p>
        <a href="{{ route('tenant.rentals.create') }}"
           class="mt-6 inline-block rounded-full bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700">
            Sewa Baru
        </a>
    </div>
@endforelse
@endsection
 