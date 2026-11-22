@extends('layouts.app')

@section('title', 'Laporan Sewa Garasi')

@php
    $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $angka = fn ($value) => number_format((float) $value, 0, ',', '.');

    $monthNames = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    // Label kolom pertama dan isi tiap baris, sesuai jenis laporan
    $periodHeader = match ($type) {
        'bulanan' => 'Bulan',
        'harian' => 'Tanggal',
        'tipe' => 'Tipe Garasi',
        'penyewa' => 'Penyewa',
    };

    $periodLabel = function (array $row) use ($type, $month, $year, $monthNames) {
        return match ($type) {
            'bulanan' => $monthNames[$row['period']],
            'harian' => $row['period'].' '.$monthNames[$month].' '.$year,
            'tipe' => \App\Enums\GarageType::from($row['period'])->label(),
            'penyewa' => $row['period'],
        };
    };

    $subtitle = $type === 'harian' || ($month && $type !== 'bulanan')
        ? $monthNames[$month].' '.$year
        : 'Tahun '.$year;

    $input = 'w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100';
@endphp

@section('content')
<div class="mb-8">
    <p class="text-sm font-medium text-blue-600">Admin</p>
    <h1 class="text-3xl font-bold text-slate-900">Laporan Sewa Garasi</h1>
</div>

{{-- PILIHAN JENIS LAPORAN --}}
<div class="mb-4 flex flex-wrap gap-2">
    @foreach ($types as $key => $label)
        <a href="{{ route('admin.reports.index', ['type' => $key, 'year' => $year, 'month' => $key === 'bulanan' ? null : $month]) }}"
           class="rounded-full px-5 py-2 text-sm font-semibold {{ $type === $key ? 'bg-blue-600 text-white' : 'border border-slate-300 bg-white text-slate-600 hover:bg-slate-50' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- FILTER --}}
<form method="GET" action="{{ route('admin.reports.index') }}"
      class="mb-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <input type="hidden" name="type" value="{{ $type }}">

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="year" class="mb-1 block text-sm font-medium text-slate-700">Tahun</label>
            <select id="year" name="year" class="{{ $input }}">
                @foreach ($years as $option)
                    <option value="{{ $option }}" @selected($year === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>

        @if ($type !== 'bulanan')
            <div>
                <label for="month" class="mb-1 block text-sm font-medium text-slate-700">Bulan</label>
                <select id="month" name="month" class="{{ $input }}">
                    @if ($type !== 'harian')
                        <option value="">Semua bulan</option>
                    @endif
                    @foreach ($monthNames as $number => $name)
                        <option value="{{ $number }}" @selected($month === $number)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="flex items-end">
            <button type="submit" class="rounded-full bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                Tampilkan
            </button>
        </div>
    </div>
</form>

{{-- KARTU RINGKASAN --}}
<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
    <div class="rounded-3xl bg-blue-950 p-5 text-white">
        <p class="text-sm text-blue-200">Total Pendapatan</p>
        <p class="mt-2 text-2xl font-bold">{{ $rupiah($totals['total_income']) }}</p>
    </div>
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Pendapatan Sewa</p>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $rupiah($totals['rent_income']) }}</p>
    </div>
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Pendapatan Charging</p>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $rupiah($totals['charging_income']) }}</p>
    </div>
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Sewa Baru</p>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $totals['total_rentals'] }}</p>
    </div>
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Perpanjangan</p>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $totals['total_extensions'] }}</p>
    </div>
</div>

{{-- TABEL PENDAPATAN --}}
<div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="font-semibold text-slate-900">{{ $types[$type] }}</h2>
        <p class="text-sm text-slate-500">{{ $subtitle }}</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-160 text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ $periodHeader }}</th>
                    <th class="px-4 py-3 text-right">Sewa Baru</th>
                    <th class="px-4 py-3 text-right">Perpanjangan</th>
                    <th class="px-4 py-3 text-right">Pendapatan Sewa</th>
                    <th class="px-4 py-3 text-right">Pendapatan Charging</th>
                    <th class="px-4 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <span class="font-medium text-slate-900">{{ $periodLabel($row) }}</span>
                            @isset($row['email'])
                                <span class="block text-xs text-slate-500">{{ $row['email'] }}</span>
                            @endisset
                        </td>
                        <td class="px-4 py-3 text-right">{{ $row['total_rentals'] }}</td>
                        <td class="px-4 py-3 text-right">{{ $row['total_extensions'] }}</td>
                        <td class="px-4 py-3 text-right">{{ $angka($row['rent_income']) }}</td>
                        <td class="px-4 py-3 text-right">{{ $angka($row['charging_income']) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ $angka($row['total_income']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-slate-500">Belum ada transaksi untuk periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="bg-blue-950 text-white">
                <tr>
                    <td class="px-4 py-4 font-bold">TOTAL</td>
                    <td class="px-4 py-4 text-right font-bold">{{ $totals['total_rentals'] }}</td>
                    <td class="px-4 py-4 text-right font-bold">{{ $totals['total_extensions'] }}</td>
                    <td class="px-4 py-4 text-right font-bold">{{ $angka($totals['rent_income']) }}</td>
                    <td class="px-4 py-4 text-right font-bold">{{ $angka($totals['charging_income']) }}</td>
                    <td class="px-4 py-4 text-right font-bold">{{ $angka($totals['total_income']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
