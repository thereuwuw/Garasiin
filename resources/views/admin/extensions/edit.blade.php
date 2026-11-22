@extends('layouts.app')
 
@section('title', 'Perpanjang '.$rental->garage->code)
 
@php
    $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $label = 'mb-1 block text-sm font-medium text-slate-700';
    $vehicle = $rental->vehicle;
@endphp
 
@section('content')
<div class="mb-8">
    <a href="{{ route('admin.extensions.index') }}" class="text-sm font-medium text-blue-600 hover:underline">← Perpanjangan Sewa</a>
    <h1 class="mt-2 text-3xl font-bold text-slate-900">Perpanjang Sewa {{ $rental->garage->code }}</h1>
</div>
 
@if ($errors->any())
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
        <ul class="list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
 
<div class="grid gap-6 lg:grid-cols-3">
    {{-- Detail sewa (hanya dibaca) --}}
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 lg:col-span-2">
        <h2 class="mb-6 text-lg font-bold text-slate-900">Detail sewa</h2>
 
        <dl class="grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-slate-500">Garasi</dt>
                <dd class="font-semibold text-slate-900">{{ $rental->garage->code }} · {{ $rental->garage->type->label() }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Harga harian</dt>
                <dd class="font-semibold text-slate-900">{{ $rupiah($rental->garage->daily_price) }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Penyewa</dt>
                <dd class="font-semibold text-slate-900">{{ $rental->user->name }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Kendaraan</dt>
                <dd class="font-semibold text-slate-900">
                    {{ $vehicle->plate_number ?? 'Tanpa plat' }} · {{ $vehicle->brand }} {{ $vehicle->model }}
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Tipe sewa awal</dt>
                <dd class="font-semibold text-slate-900">{{ $rental->rental_type->label() }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Periode awal</dt>
                <dd class="font-semibold text-slate-900">
                    {{ $rental->start_date->format('d-m-Y') }} s/d {{ $rental->end_date->format('d-m-Y') }}
                </dd>
            </div>
        </dl>
 
        @if ($rental->extensions->isNotEmpty())
            <div class="mt-6 border-t border-slate-100 pt-4">
                <p class="mb-2 text-sm font-semibold text-slate-700">Perpanjangan sebelumnya</p>
                <ul class="space-y-1 text-sm text-slate-600">
                    @foreach ($rental->extensions->sortBy('start_date') as $extension)
                        <li class="flex justify-between">
                            <span>{{ $extension->start_date->format('d-m-Y') }} s/d {{ $extension->end_date->format('d-m-Y') }} ({{ $extension->duration_days }} hari)</span>
                            <span class="font-medium text-slate-900">{{ $rupiah($extension->rent_cost) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
 
        <div class="mt-6 rounded-2xl bg-blue-50 px-5 py-4">
            <p class="text-sm text-blue-700">Tanggal selesai saat ini</p>
            <p class="text-2xl font-bold text-blue-950">{{ $currentEnd->format('d-m-Y') }}</p>
        </div>
    </section>
 
    {{-- Form perpanjangan --}}
    <form method="POST" action="{{ route('admin.extensions.store', $rental) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
 
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <label for="new_end_date" class="{{ $label }}">Tanggal selesai baru</label>
            <input id="new_end_date" name="new_end_date" type="date" min="{{ $minNewEnd }}"
                   value="{{ old('new_end_date') }}" required
                   class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100">
            @error('new_end_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </section>
 
        <section class="rounded-3xl bg-blue-950 p-6 text-white shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-widest text-blue-300">Sisa biaya (harian)</p>
            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="text-blue-200">Hari tambahan</dt>
                    <dd id="ext-days" class="font-semibold">-</dd>
                </div>
                <div>
                    <dt class="text-blue-200">Perhitungan</dt>
                    <dd id="ext-calc" class="font-semibold">-</dd>
                </div>
            </dl>
            <div class="mt-4 border-t border-white/15 pt-4">
                <p class="text-sm text-blue-200">Total</p>
                <p id="ext-total" class="text-3xl font-bold">Rp 0</p>
            </div>
            <p id="ext-error" class="mt-3 hidden rounded-xl bg-red-500/20 px-3 py-2 text-sm text-red-100"></p>
        </section>
 
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <label for="payment_proof" class="{{ $label }}">Bukti bayar perpanjangan (gambar, maks. 2 MB)</label>
            <input id="payment_proof" name="payment_proof" type="file" accept="image/*" required
                   class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-full file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:font-semibold file:text-blue-700">
            @error('payment_proof') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
 
            <button type="submit" class="mt-6 w-full rounded-full bg-blue-600 py-3 text-sm font-semibold text-white hover:bg-blue-700">
                Simpan
            </button>
        </section>
    </form>
</div>
@endsection
 
@push('scripts')
<script>
(() => {
    const previewUrl = @json(route('admin.extensions.preview', $rental));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const newEnd = document.getElementById('new_end_date');
    const $ = (id) => document.getElementById(id);
 
    const rupiah = (n) => 'Rp ' + Math.round(Number(n)).toLocaleString('id-ID');
    const formatDate = (s) => s.split('-').reverse().join('-'); // 2026-11-27 -> 27-11-2026
 
    function reset(error = null) {
        $('ext-days').textContent = '-';
        $('ext-calc').textContent = '-';
        $('ext-total').textContent = 'Rp 0';
        $('ext-error').classList.toggle('hidden', !error);
        $('ext-error').textContent = error ?? '';
    }
 
    // Hitung sisa biaya di server setiap tanggal berubah
    async function loadPreview() {
        if (!newEnd.value) { reset(); return; }
 
        const response = await fetch(previewUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ new_end_date: newEnd.value }),
        });
        const data = await response.json();
 
        if (!response.ok) { reset(data.message ?? 'Biaya tidak bisa dihitung.'); return; }
 
        reset();
        $('ext-days').textContent = `${formatDate(data.start_date)} s/d ${formatDate(data.end_date)} (${data.extra_days} hari)`;
        $('ext-calc').textContent = `${data.extra_days} × ${rupiah(data.daily_price)}`;
        $('ext-total').textContent = rupiah(data.rent_cost);
    }
 
    newEnd.addEventListener('change', loadPreview);
    loadPreview();
})();
</script>
@endpush
 