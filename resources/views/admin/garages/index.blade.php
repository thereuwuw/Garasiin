@extends('layouts.app')
 
@section('title', 'Daftar Garasi')
 
@php
    $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $tab = fn (bool $active) => $active
        ? 'rounded-full bg-blue-950 px-4 py-2 text-sm font-semibold text-white'
        : 'rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:border-slate-400';
@endphp
 
@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-sm font-medium text-blue-600">Admin</p>
        <h1 class="text-3xl font-bold text-slate-900">Daftar Garasi</h1>
    </div>
    <a href="{{ route('admin.garages.create') }}"
       class="rounded-full bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700">
        + Tambah Garasi
    </a>
</div>
 
{{-- Jumlah garasi per tipe --}}
<div class="mb-6 grid gap-4 sm:grid-cols-3">
    @foreach ($types as $type)
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">{{ $type->label() }}</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $counts[$type->value] ?? 0 }}</p>
            <p class="text-xs text-slate-400">kode {{ $type->prefix() }}001 – {{ $type->prefix() }}999</p>
        </div>
    @endforeach
</div>
 
{{-- Filter tipe --}}
<div class="mb-4 flex flex-wrap gap-2">
    <a href="{{ route('admin.garages.index') }}" class="{{ $tab($selectedType === null) }}">Semua</a>
    @foreach ($types as $type)
        <a href="{{ route('admin.garages.index', ['type' => $type->value]) }}" class="{{ $tab($selectedType === $type) }}">
            {{ $type->label() }}
        </a>
    @endforeach
</div>
 
<div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-6 py-4">Kode</th>
                    <th class="px-6 py-4">Tipe</th>
                    <th class="px-6 py-4 text-right">Harga harian</th>
                    <th class="px-6 py-4 text-right">Harga bulanan</th>
                    <th class="px-6 py-4 text-right">Listrik / kWh</th>
                    <th class="px-6 py-4">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($garages as $garage)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 font-bold text-slate-900">{{ $garage->code }}</td>
                        <td class="px-6 py-4">{{ $garage->type->label() }}</td>
                        <td class="px-6 py-4 text-right">{{ $rupiah($garage->daily_price) }}</td>
                        <td class="px-6 py-4 text-right">
                            {{ $rupiah($garage->daily_price * \App\Services\RentalCostService::MONTHLY_PRICE_FACTOR) }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            {{ $garage->electricity_price_per_kwh !== null ? $rupiah($garage->electricity_price_per_kwh) : '-' }}
                        </td>
                        <td class="px-6 py-4">
                            @if ($garage->status === \App\Enums\GarageStatus::Used)
                                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">Dipakai</span>
                            @else
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Tersedia</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500">Belum ada garasi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
 
<div class="mt-6">
    {{ $garages->links() }}
</div>
@endsection
 