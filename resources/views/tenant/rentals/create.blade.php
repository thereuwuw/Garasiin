@extends('layouts.app')
 
@section('title', 'Sewa Baru')
 
@php
    $input = 'w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100';
    $label = 'mb-1 block text-sm font-medium text-slate-700';
    $card = 'rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8';
 
    // Data untuk JavaScript
    $garageLabels = collect(\App\Enums\GarageType::cases())
        ->mapWithKeys(fn ($type) => [$type->value => $type->label()]);
 
    $savedVehicles = $vehicles->map(fn ($vehicle) => [
        'id' => $vehicle->id,
        'type' => $vehicle->type->value,
        'plate_number' => $vehicle->plate_number,
        'brand' => $vehicle->brand,
        'model' => $vehicle->model,
        'max_battery_capacity_kwh' => $vehicle->max_battery_capacity_kwh !== null
            ? (float) $vehicle->max_battery_capacity_kwh
            : null,
    ])->values();
@endphp
 
@section('content')
<div class="mb-8">
    <a href="{{ route('tenant.rentals.index') }}" class="text-sm font-medium text-blue-600 hover:underline">← Sewa Saya</a>
    <h1 class="mt-2 text-3xl font-bold text-slate-900">Sewa Baru</h1>
    <p class="mt-1 text-sm text-slate-500">Isi data kendaraan, pilih periode dan garasi, lalu upload bukti pembayaran.</p>
</div>
 
{{-- Semua error (termasuk error dari RentalService yang disimpan di key "rental") --}}
@if ($errors->any())
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
        <p class="font-semibold">Sewa belum bisa disimpan:</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <p class="mt-2 text-red-600">File bukti bayar perlu diupload ulang.</p>
    </div>
@endif
 
<form method="POST" action="{{ route('tenant.rentals.store') }}" enctype="multipart/form-data"
      id="rental-form" class="grid gap-6 lg:grid-cols-3">
    @csrf
 
    <div class="space-y-6 lg:col-span-2">
        {{-- 1. Detail kendaraan --}}
        <section class="{{ $card }}">
            <div class="mb-6 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-950 text-sm font-bold text-white">1</span>
                <h2 class="text-lg font-bold text-slate-900">Detail kendaraan</h2>
            </div>
 
            @if ($vehicles->isNotEmpty())
                <div class="mb-5">
                    <label for="saved_vehicle" class="{{ $label }}">Pakai kendaraan yang pernah didaftarkan (opsional)</label>
                    {{-- Tanpa atribut name: hanya untuk mengisi form otomatis, tidak ikut dikirim --}}
                    <select id="saved_vehicle" class="{{ $input }}">
                        <option value="">Isi manual</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">
                                {{ $vehicle->plate_number ?? 'Tanpa plat' }} · {{ $vehicle->brand }} {{ $vehicle->model }} ({{ $vehicle->type->label() }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
 
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="vehicle_type" class="{{ $label }}">Tipe kendaraan</label>
                    <select id="vehicle_type" name="vehicle_type" required class="{{ $input }}">
                        <option value="">Pilih tipe kendaraan</option>
                        @foreach ($vehicleTypes as $type)
                            <option value="{{ $type->value }}" @selected(old('vehicle_type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('vehicle_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
 
                <div id="plate-wrap" class="sm:col-span-2">
                    <label for="plate_number" class="{{ $label }}">Nomor plat</label>
                    <input id="plate_number" name="plate_number" type="text" maxlength="15" placeholder="L 1234 AB"
                           value="{{ old('plate_number') }}" class="{{ $input }} uppercase">
                    @error('plate_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
 
                <div>
                    <label for="brand" class="{{ $label }}">Merek</label>
                    <input id="brand" name="brand" type="text" maxlength="100" placeholder="Honda"
                           value="{{ old('brand') }}" required class="{{ $input }}">
                    @error('brand') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
 
                <div>
                    <label for="model" class="{{ $label }}">Tipe / model</label>
                    <input id="model" name="model" type="text" maxlength="100" placeholder="Vario 160"
                           value="{{ old('model') }}" required class="{{ $input }}">
                    @error('model') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
 
                <div id="battery-wrap" class="sm:col-span-2">
                    <label for="max_battery_capacity_kwh" class="{{ $label }}">Kapasitas baterai (kWh)</label>
                    <input id="max_battery_capacity_kwh" name="max_battery_capacity_kwh" type="number" min="1" step="0.01"
                           placeholder="60" value="{{ old('max_battery_capacity_kwh') }}" class="{{ $input }}">
                    @error('max_battery_capacity_kwh') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>
 
        {{-- 2. Periode sewa --}}
        <section class="{{ $card }}">
            <div class="mb-6 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-950 text-sm font-bold text-white">2</span>
                <h2 class="text-lg font-bold text-slate-900">Periode sewa</h2>
            </div>
 
            <div class="mb-5 grid grid-cols-2 gap-3">
                @foreach (\App\Enums\RentalType::cases() as $type)
                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-300 px-4 py-3 has-checked:border-blue-600 has-checked:bg-blue-50">
                        <input type="radio" name="rental_type" value="{{ $type->value }}" class="text-blue-600"
                               @checked(old('rental_type', 'harian') === $type->value)>
                        <span>
                            <span class="block font-semibold text-slate-900">{{ $type->label() }}</span>
                            <span class="block text-xs text-slate-500">
                                {{ $type === \App\Enums\RentalType::Harian ? 'Bayar per hari' : '25 × harga harian' }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('rental_type') <p class="mb-4 text-sm text-red-600">{{ $message }}</p> @enderror
 
            <div class="grid gap-5 sm:grid-cols-3">
                <div>
                    <label for="start_date" class="{{ $label }}">Tanggal mulai</label>
                    <input id="start_date" name="start_date" type="date" min="{{ $startDate }}"
                           value="{{ old('start_date', $startDate) }}" required class="{{ $input }}">
                    @error('start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
 
                <div id="days-wrap">
                    <label for="days" class="{{ $label }}">Jumlah hari</label>
                    <input id="days" name="days" type="number" min="1" max="{{ $maxDailyDays }}"
                           value="{{ old('days', 1) }}" class="{{ $input }}">
                    <p id="days-hint" class="mt-1 text-xs text-slate-500">Maksimal {{ $maxDailyDays }} hari.</p>
                    @error('days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
 
                <div>
                    <label for="end_date_display" class="{{ $label }}">Tanggal selesai</label>
                    {{-- Hanya tampilan, dihitung otomatis dan tidak dikirim --}}
                    <input id="end_date_display" type="text" readonly tabindex="-1"
                           class="{{ $input }} cursor-not-allowed bg-slate-100 font-semibold text-slate-900">
                </div>
            </div>
        </section>
 
        {{-- 3. Pilih garasi --}}
        <section class="{{ $card }}">
            <div class="mb-6 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-950 text-sm font-bold text-white">3</span>
                <h2 class="text-lg font-bold text-slate-900">Pilih garasi</h2>
            </div>
 
            <div class="mb-5">
                <label for="garage_type" class="{{ $label }}">Tipe garasi</label>
                <select id="garage_type" name="garage_type" class="{{ $input }}">
                    <option value="">Pilih tipe kendaraan dulu</option>
                </select>
            </div>
 
            <p id="garage-message" class="rounded-2xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                Lengkapi tipe kendaraan, periode sewa, dan tipe garasi untuk melihat garasi yang tersedia.
            </p>
            <div id="garage-list" class="grid grid-cols-2 gap-3 sm:grid-cols-4"></div>
            @error('garage_id') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </section>
 
        {{-- 4. Isi listrik: hanya untuk mobil listrik di garasi mobil listrik --}}
        <section id="charging-section" class="{{ $card }} hidden">
            <div class="mb-6 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">⚡</span>
                <h2 class="text-lg font-bold text-slate-900">Isi listrik</h2>
            </div>
 
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="current_battery_percent" class="{{ $label }}">Baterai saat ini (%)</label>
                    <input id="current_battery_percent" name="current_battery_percent" type="number" min="0" max="100" step="0.01"
                           placeholder="60" value="{{ old('current_battery_percent') }}" class="{{ $input }}">
                    @error('current_battery_percent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
 
                <div class="rounded-2xl bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    <p class="text-blue-700">Kebutuhan listrik sampai penuh</p>
                    <p id="charging-info" class="mt-1 font-semibold">-</p>
                </div>
 
                <div id="charging-proof-wrap" class="sm:col-span-2">
                    <label for="charging_payment_proof" class="{{ $label }}">Bukti bayar isi listrik (gambar, maks. 2 MB)</label>
                    <input id="charging_payment_proof" name="charging_payment_proof" type="file" accept="image/*"
                           class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-full file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:font-semibold file:text-blue-700">
                    @error('charging_payment_proof') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>
 
        {{-- 4. Bukti pelunasan sewa --}}
        <section class="{{ $card }}">
            <div class="mb-6 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-950 text-sm font-bold text-white">4</span>
                <h2 class="text-lg font-bold text-slate-900">Bukti pelunasan sewa</h2>
            </div>
 
            <label for="payment_proof" class="{{ $label }}">Upload bukti pelunasan sewa (gambar, maks. 2 MB)</label>
            <input id="payment_proof" name="payment_proof" type="file" accept="image/*" required
                   class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-full file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:font-semibold file:text-blue-700">
            @error('payment_proof') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </section>
 
        <button type="submit"
                class="w-full rounded-full bg-blue-600 py-3 text-sm font-semibold text-white hover:bg-blue-700">
            Simpan
        </button>
    </div>
 
    {{-- Ringkasan biaya --}}
    <aside class="lg:col-span-1">
        <div class="sticky top-6 space-y-6">
            <section class="rounded-3xl bg-blue-950 p-6 text-white shadow-sm sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-widest text-blue-300">Ringkasan biaya</p>
 
                <dl class="mt-5 space-y-4 text-sm">
                    <div>
                        <dt class="text-blue-200">Garasi</dt>
                        <dd id="sum-garage" class="font-semibold">-</dd>
                    </div>
                    <div>
                        <dt class="text-blue-200">Periode</dt>
                        <dd id="sum-period" class="font-semibold">-</dd>
                    </div>
                    <div>
                        <dt class="text-blue-200">Biaya sewa</dt>
                        <dd id="sum-rent" class="font-semibold">-</dd>
                    </div>
                    <div id="sum-charging-row" class="hidden">
                        <dt class="text-blue-200">Isi listrik</dt>
                        <dd id="sum-charging" class="font-semibold">-</dd>
                    </div>
                </dl>
 
                <div class="mt-6 border-t border-white/15 pt-4">
                    <p class="text-sm text-blue-200">Total</p>
                    <p id="sum-total" class="text-3xl font-bold">Rp 0</p>
                </div>
                <p id="sum-error" class="mt-3 hidden rounded-xl bg-red-500/20 px-3 py-2 text-sm text-red-100"></p>
            </section>
 
        </div>
    </aside>
</form>
@endsection
 
@push('scripts')
<script>
(() => {
    // ===== Data dari controller =====
    const garageTypesByVehicle = @json($garageTypesByVehicle);
    const garageLabels = @json($garageLabels);
    const savedVehicles = @json($savedVehicles);
    const today = @json($startDate);
    const garagesUrl = @json(route('tenant.rentals.garages'));
    const previewUrl = @json(route('tenant.rentals.preview'));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
 
    // Nilai lama kalau form kembali karena error
    let selectedGarageId = @json(old('garage_id'));
    const oldGarageType = @json(old('garage_type'));
 
    // ===== Elemen =====
    const $ = (id) => document.getElementById(id);
    const vehicleType = $('vehicle_type');
    const garageType = $('garage_type');
    const startDate = $('start_date');
    const days = $('days');
    const battery = $('current_battery_percent');
    const capacity = $('max_battery_capacity_kwh');
    let garages = [];
    let garageRequest = 0; // supaya respons lama tidak menimpa respons terbaru
 
    // ===== Helper =====
    const pad = (n) => String(n).padStart(2, '0');
    const parseDate = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const formatDate = (d) => `${pad(d.getDate())}-${pad(d.getMonth() + 1)}-${d.getFullYear()}`;
    const daysInMonth = (d) => new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
    const rupiah = (n) => 'Rp ' + Math.round(Number(n)).toLocaleString('id-ID');
    const number = (n) => Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    const rentalType = () => document.querySelector('input[name="rental_type"]:checked')?.value ?? '';
 
    // Tampilkan/sembunyikan bagian form. Input di bagian yang tersembunyi di-disable supaya tidak ikut terkirim.
    const toggle = (el, show) => {
        el.classList.toggle('hidden', !show);
        el.querySelectorAll('input, select').forEach((input) => input.disabled = !show);
    };
 
    let timer;
    const debounce = (fn) => { clearTimeout(timer); timer = setTimeout(fn, 300); };
 
    // ===== Langkah 2: form kendaraan menyesuaikan tipe =====
    function updateVehicleFields() {
        const type = vehicleType.value;
 
        toggle($('plate-wrap'), type !== '' && type !== 'sepeda');
        $('plate_number').required = type !== '' && type !== 'sepeda';
 
        toggle($('battery-wrap'), type === 'mobil_listrik');
        capacity.required = type === 'mobil_listrik';
 
        // Langkah 7: pilihan tipe garasi sesuai tipe kendaraan
        const allowed = garageTypesByVehicle[type] ?? [];
        const current = garageType.value || oldGarageType;
        garageType.innerHTML = allowed.length
            ? '<option value="">Pilih tipe garasi</option>'
            : '<option value="">Pilih tipe kendaraan dulu</option>';
        allowed.forEach((value) => garageType.add(new Option(garageLabels[value], value)));
        // Kalau pilihannya cuma satu, langsung dipilih
        garageType.value = allowed.includes(current) ? current : (allowed.length === 1 ? allowed[0] : '');
    }
 
    // ===== Langkah 3–6: periode sewa =====
    function updatePeriod() {
        const isDaily = rentalType() === 'harian';
        toggle($('days-wrap'), isDaily);
        days.required = isDaily;
 
        if (!startDate.value) {
            $('end_date_display').value = '';
            return;
        }
 
        const start = parseDate(startDate.value);
        const monthDays = daysInMonth(start);
        const maxDays = monthDays - 1;
        days.max = maxDays;
        $('days-hint').textContent = `Maksimal ${maxDays} hari untuk bulan ini.`;
 
        // Tanggal selesai = tanggal mulai + jumlah hari - 1
        const total = isDaily ? Number(days.value) : monthDays;
        if (!total || total < 1 || (isDaily && total > maxDays)) {
            $('end_date_display').value = '';
            return;
        }
 
        const end = new Date(start);
        end.setDate(end.getDate() + total - 1);
        $('end_date_display').value = formatDate(end);
    }
 
    // ===== Langkah 8: daftar garasi tersedia (AJAX) =====
    async function loadGarages() {
        const message = $('garage-message');
        const list = $('garage-list');
        list.innerHTML = '';
        garages = [];
        updateCharging();
 
        if (!vehicleType.value || !garageType.value || !rentalType() || !startDate.value) {
            message.textContent = 'Lengkapi tipe kendaraan, periode sewa, dan tipe garasi untuk melihat garasi yang tersedia.';
            message.classList.remove('hidden');
            renderSummary(null);
            return;
        }
 
        message.textContent = 'Memuat garasi...';
        message.classList.remove('hidden');
 
        const params = new URLSearchParams({
            vehicle_type: vehicleType.value,
            garage_type: garageType.value,
            rental_type: rentalType(),
            start_date: startDate.value,
        });
        if (rentalType() === 'harian') params.append('days', days.value);
 
        const requestId = ++garageRequest;
        const response = await fetch(`${garagesUrl}?${params}`, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (requestId !== garageRequest) return;
 
        if (!response.ok) {
            message.textContent = data.message ?? 'Garasi tidak bisa dimuat.';
            renderSummary(null);
            return;
        }
 
        garages = data.garages;
 
        if (garages.length === 0) {
            message.textContent = 'Tidak ada garasi tersedia.';
            renderSummary(null);
            return;
        }
 
        message.classList.add('hidden');
 
        garages.forEach((garage) => {
            const label = document.createElement('label');
            label.className = 'cursor-pointer rounded-2xl border border-slate-300 p-4 text-center has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50';
            label.innerHTML = `
                <input type="radio" name="garage_id" value="${garage.id}" class="sr-only" required>
                <span class="block text-lg font-bold text-slate-900">${garage.code}</span>
                <span class="block text-xs text-slate-500">${rupiah(garage.daily_price)}/hari</span>`;
            const radio = label.querySelector('input');
            radio.checked = garage.id === selectedGarageId;
            radio.addEventListener('change', () => { selectedGarageId = garage.id; updateCharging(); loadPreview(); });
            list.appendChild(label);
        });
 
        // Garasi yang dipilih sebelumnya sudah tidak tersedia
        if (!garages.some((g) => g.id === selectedGarageId)) selectedGarageId = null;
 
        updateCharging();
        loadPreview();
    }
 
    // ===== Alur khusus mobil listrik di garasi mobil listrik =====
    function needsCharging() {
        return vehicleType.value === 'mobil_listrik' && garageType.value === 'mobil_listrik';
    }
 
    function updateCharging() {
        const show = needsCharging();
        toggle($('charging-section'), show);
        toggle($('sum-charging-row'), show);
        battery.required = show;
 
        if (!show) return;
 
        const garage = garages.find((g) => g.id === selectedGarageId);
        const cap = Number(capacity.value);
        const percent = battery.value === '' ? null : Number(battery.value);
 
        if (!cap || percent === null) {
            $('charging-info').textContent = '-';
        } else {
            const kwh = cap * (100 - percent) / 100;
            const price = garage?.electricity_price_per_kwh;
            $('charging-info').textContent = price
                ? `${number(cap)} kWh × (100 − ${number(percent)})% = ${number(kwh)} kWh → ${rupiah(kwh * price)}`
                : `${number(kwh)} kWh`;
        }
 
        // Baterai 100%: tidak ada biaya listrik dan tidak perlu bukti bayar
        const full = percent !== null && percent >= 100;
        toggle($('charging-proof-wrap'), !full);
        $('charging_payment_proof').required = !full;
    }
 
    // ===== Langkah 9: ringkasan biaya dari server =====
    async function loadPreview() {
        const garage = garages.find((g) => g.id === selectedGarageId);
        if (!garage) { renderSummary(null); return; }
 
        const response = await fetch(previewUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({
                garage_id: garage.id,
                rental_type: rentalType(),
                days: rentalType() === 'harian' ? days.value : null,
                start_date: startDate.value,
                vehicle_type: vehicleType.value,
                max_battery_capacity_kwh: capacity.value || null,
                current_battery_percent: battery.value || null,
            }),
        });
        const data = await response.json();
 
        if (!response.ok) {
            renderSummary(null, data.message ?? 'Biaya tidak bisa dihitung.');
            return;
        }
 
        renderSummary({ ...data, garage });
    }
 
    function renderSummary(data, error = null) {
        $('sum-error').classList.toggle('hidden', !error);
        $('sum-error').textContent = error ?? '';
 
        if (!data) {
            ['sum-garage', 'sum-period', 'sum-rent', 'sum-charging'].forEach((id) => $(id).textContent = '-');
            $('sum-total').textContent = 'Rp 0';
            return;
        }
 
        const start = formatDate(parseDate(data.start_date));
        const end = formatDate(parseDate(data.end_date));
        const multiplier = rentalType() === 'bulanan' ? 25 : data.days;
 
        $('sum-garage').textContent = `${data.garage.code} · ${data.garage.type_label}`;
        $('sum-period').textContent = `${start} s/d ${end} (${data.days} hari)`;
        $('sum-rent').textContent = `${multiplier} × ${rupiah(data.daily_price)} = ${rupiah(data.rent_cost)}`;
        $('sum-charging').textContent = data.charging_cost > 0
            ? `${number(data.charge_needed_kwh)} kWh × ${rupiah(data.garage.electricity_price_per_kwh)} = ${rupiah(data.charging_cost)}`
            : 'Rp 0';
        $('sum-total').textContent = rupiah(data.total_cost);
    }
 
    // ===== Isi form dari kendaraan tersimpan =====
    $('saved_vehicle')?.addEventListener('change', (e) => {
        const vehicle = savedVehicles.find((v) => v.id === e.target.value);
        if (!vehicle) return;
        vehicleType.value = vehicle.type;
        $('plate_number').value = vehicle.plate_number ?? '';
        $('brand').value = vehicle.brand;
        $('model').value = vehicle.model;
        capacity.value = vehicle.max_battery_capacity_kwh ?? '';
        updateVehicleFields();
        loadGarages();
    });
 
    // ===== Event =====
    vehicleType.addEventListener('change', () => { updateVehicleFields(); loadGarages(); });
    garageType.addEventListener('change', loadGarages);
    document.querySelectorAll('input[name="rental_type"]').forEach((radio) =>
        radio.addEventListener('change', () => { updatePeriod(); loadGarages(); }));
    startDate.addEventListener('change', () => {
        if (startDate.value && startDate.value < today) startDate.value = today; // tidak boleh sebelum hari ini
        updatePeriod();
        loadGarages();
    });
    days.addEventListener('input', () => { updatePeriod(); debounce(loadGarages); });
    capacity.addEventListener('input', () => { updateCharging(); debounce(loadPreview); });
    battery.addEventListener('input', () => { updateCharging(); debounce(loadPreview); });
 
    // ===== Saat halaman dibuka (termasuk kembali karena error) =====
    updateVehicleFields();
    updatePeriod();
    loadGarages();
})();
</script>
@endpush
 