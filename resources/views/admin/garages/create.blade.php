@extends('layouts.app')
 
@section('title', 'Tambah Garasi')
 
@php
    $input = 'w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100';
    $label = 'mb-1 block text-sm font-medium text-slate-700';
@endphp
 
@section('content')
<div class="mb-8">
    <a href="{{ route('admin.garages.index') }}" class="text-sm font-medium text-blue-600 hover:underline">← Daftar Garasi</a>
    <h1 class="mt-2 text-3xl font-bold text-slate-900">Tambah Garasi</h1>
    <p class="mt-1 text-sm text-slate-500">Buat beberapa garasi sekaligus. Kode melanjutkan nomor terakhir tipe yang dipilih.</p>
</div>
 
{{-- Error dari GarageService, misalnya "Sisa kuota ... tinggal X." --}}
@error('garage')
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">{{ $message }}</div>
@enderror
 
<form method="POST" action="{{ route('admin.garages.store') }}" class="grid gap-6 lg:grid-cols-3">
    @csrf
 
    <section class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 lg:col-span-2">
        {{-- Tipe garasi --}}
        <div>
            <p class="{{ $label }}">Tipe garasi</p>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ($types as $type)
                    <label class="cursor-pointer rounded-2xl border border-slate-300 p-4 has-checked:border-blue-600 has-checked:bg-blue-50">
                        <input type="radio" name="type" value="{{ $type->value }}" class="text-blue-600" required
                               @checked(old('type') === $type->value)>
                        <span class="mt-2 block font-semibold text-slate-900">{{ $type->label() }}</span>
                        <span class="block text-xs text-slate-500">Kode {{ $type->prefix() }}001, {{ $type->prefix() }}002, ...</span>
                    </label>
                @endforeach
            </div>
            @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
 
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="quantity" class="{{ $label }}">Jumlah garasi</label>
                <input id="quantity" name="quantity" type="number" min="1" max="{{ $maxPerType }}"
                       value="{{ old('quantity', 1) }}" required class="{{ $input }}">
                <p id="quota-hint" class="mt-1 text-xs text-slate-500">Pilih tipe garasi untuk melihat sisa kuota.</p>
                @error('quantity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
 
            <div>
                <label for="daily_price" class="{{ $label }}">Harga sewa harian (Rp)</label>
                <input id="daily_price" name="daily_price" type="number" min="1" step="1"
                       value="{{ old('daily_price') }}" required class="{{ $input }}">
                @error('daily_price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
 
            <div id="electricity-wrap" class="hidden sm:col-span-2">
                <label for="electricity_price_per_kwh" class="{{ $label }}">Harga isi listrik per kWh (Rp)</label>
                <input id="electricity_price_per_kwh" name="electricity_price_per_kwh" type="number" min="1" step="1"
                       value="{{ old('electricity_price_per_kwh') }}" class="{{ $input }}">
                @error('electricity_price_per_kwh') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>
 
    <aside class="space-y-6">
        <section class="rounded-3xl bg-blue-950 p-6 text-white shadow-sm sm:p-8">
            <p class="text-xs font-semibold uppercase tracking-widest text-blue-300">Harga bulanan</p>
            <p id="monthly-price" class="mt-3 text-3xl font-bold">Rp 0</p>
            <p class="mt-2 text-sm text-blue-200">Otomatis 25 × harga harian.</p>
        </section>
 
        <button type="submit" class="w-full rounded-full bg-blue-600 py-3 text-sm font-semibold text-white hover:bg-blue-700">
            Simpan
        </button>
    </aside>
</form>
@endsection
 
@push('scripts')
<script>
(() => {
    const factor = @json(\App\Services\RentalCostService::MONTHLY_PRICE_FACTOR);
    const remaining = @json($remaining);
    const maxPerType = @json($maxPerType);
    const quantity = document.getElementById('quantity');
    const quotaHint = document.getElementById('quota-hint');
    const dailyPrice = document.getElementById('daily_price');
    const monthly = document.getElementById('monthly-price');
    const electricityWrap = document.getElementById('electricity-wrap');
    const electricity = document.getElementById('electricity_price_per_kwh');
 
    // Harga bulanan = 25 x harga harian
    function updateMonthly() {
        const value = Number(dailyPrice.value) || 0;
        monthly.textContent = 'Rp ' + (value * factor).toLocaleString('id-ID');
    }
 
    // Sisa kuota tipe yang dipilih menjadi batas maksimal jumlah garasi
    function updateQuota() {
        const type = document.querySelector('input[name="type"]:checked')?.value;
        if (!type) return;
        const left = remaining[type];
        quantity.max = Math.max(left, 1);
        quotaHint.textContent = left > 0
            ? `Sisa kuota ${left} dari ${maxPerType} garasi. Maksimal ${left} garasi sekali buat.`
            : `Kuota tipe ini sudah penuh (${maxPerType} garasi).`;
        quotaHint.classList.toggle('text-red-600', left === 0);
    }
 
    // Input harga listrik hanya untuk Mobil Listrik
    function updateElectricity() {
        updateQuota();
        const isElectric = document.querySelector('input[name="type"]:checked')?.value === 'mobil_listrik';
        electricityWrap.classList.toggle('hidden', !isElectric);
        electricity.disabled = !isElectric;
        electricity.required = isElectric;
    }
 
    dailyPrice.addEventListener('input', updateMonthly);
    document.querySelectorAll('input[name="type"]').forEach((radio) => radio.addEventListener('change', updateElectricity));
 
    updateMonthly();
    updateElectricity();
})();
</script>
@endpush
 