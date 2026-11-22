@extends('layouts.app')
 
@section('title', 'Perpanjangan Sewa')
 
@section('content')
<div class="mb-8">
    <p class="text-sm font-medium text-blue-600">Admin</p>
    <h1 class="text-3xl font-bold text-slate-900">Perpanjangan Sewa</h1>
    <p class="mt-1 text-sm text-slate-500">Garasi yang sedang disewa hari ini ({{ today()->format('d-m-Y') }}), urut berdasarkan kode garasi.</p>
</div>
 
@error('extension')
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">{{ $message }}</div>
@enderror
 
<div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-6 py-4">Garasi</th>
                    <th class="px-6 py-4">Penyewa</th>
                    <th class="px-6 py-4">Kendaraan</th>
                    <th class="px-6 py-4">Tipe sewa</th>
                    <th class="px-6 py-4">Selesai terkini</th>
                    <th class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rentals as $rental)
                    @php $currentEnd = $currentEndDates[$rental->id]; @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <span class="font-bold text-slate-900">{{ $rental->garage->code }}</span>
                            <span class="block text-xs text-slate-500">{{ $rental->garage->type->label() }}</span>
                        </td>
                        <td class="px-6 py-4">{{ $rental->user->name }}</td>
                        <td class="px-6 py-4">
                            {{ $rental->vehicle->plate_number ?? 'Tanpa plat' }}
                            <span class="block text-xs text-slate-500">{{ $rental->vehicle->brand }} {{ $rental->vehicle->model }}</span>
                        </td>
                        <td class="px-6 py-4">
                            {{ $rental->rental_type->label() }}
                            @if ($rental->extensions->isNotEmpty())
                                <span class="block text-xs text-slate-500">{{ $rental->extensions->count() }}× diperpanjang</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-semibold text-slate-900">{{ $currentEnd->format('d-m-Y') }}</span>
                            @if ($currentEnd->isToday())
                                <span class="block text-xs font-semibold text-amber-600">Berakhir hari ini</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('admin.extensions.edit', $rental) }}"
                               class="inline-block rounded-full bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700">
                                Perpanjang
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500">Tidak ada garasi yang sedang disewa hari ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
 