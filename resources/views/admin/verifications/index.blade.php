@extends('layouts.app')

@section('title', 'Verifikasi Penyewa')

@section('content')
<div class="mb-8">
    <p class="text-sm font-medium text-blue-600">Admin</p>
    <h1 class="text-3xl font-bold text-slate-900">Verifikasi Penyewa</h1>
    <p class="mt-1 text-sm text-slate-500">Cocokkan NIK dengan KTP pendaftar sebelum menerima atau menolak.</p>
</div>

@error('verification')
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">{{ $message }}</div>
@enderror

{{-- Menunggu verifikasi (paling lama mendaftar di atas) --}}
<section class="mb-10">
    <div class="mb-4 flex items-center gap-3">
        <h2 class="text-lg font-bold text-slate-900">Menunggu Verifikasi</h2>
        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">{{ $pending->count() }}</span>
    </div>

    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-4">NIK</th>
                        <th class="px-6 py-4">Nama</th>
                        <th class="px-6 py-4">Telepon</th>
                        <th class="px-6 py-4">Email</th>
                        <th class="px-6 py-4">Tanggal daftar</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($pending as $tenant)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-mono font-semibold text-slate-900">{{ $tenant->nik }}</td>
                            <td class="px-6 py-4">{{ $tenant->name }}</td>
                            <td class="px-6 py-4">{{ $tenant->phone }}</td>
                            <td class="px-6 py-4">{{ $tenant->email }}</td>
                            <td class="px-6 py-4">{{ $tenant->created_at->format('d-m-Y') }}</td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.verifications.accept', $tenant) }}"
                                          onsubmit="return confirm(@js('Terima pendaftaran '.$tenant->name.'?'))">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-full bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700">
                                            Terima
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.verifications.reject', $tenant) }}"
                                          onsubmit="return confirm(@js('Tolak pendaftaran '.$tenant->name.'?'))">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-full border border-red-300 px-4 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">
                                            Tolak
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">Tidak ada pendaftar yang menunggu verifikasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- Sudah diverifikasi --}}
<section>
    <h2 class="mb-4 text-lg font-bold text-slate-900">Sudah Diverifikasi</h2>

    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-4">NIK</th>
                        <th class="px-6 py-4">Nama</th>
                        <th class="px-6 py-4">Email</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Diverifikasi oleh</th>
                        <th class="px-6 py-4">Waktu verifikasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($verified as $tenant)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-mono">{{ $tenant->nik }}</td>
                            <td class="px-6 py-4">{{ $tenant->name }}</td>
                            <td class="px-6 py-4">{{ $tenant->email }}</td>
                            <td class="px-6 py-4">
                                @if ($tenant->status === \App\Enums\UserStatus::Accepted)
                                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Diterima</span>
                                @else
                                    <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">Ditolak</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">{{ $tenant->verifier?->name ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $tenant->verified_at?->format('d-m-Y H:i') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">Belum ada penyewa yang diverifikasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection