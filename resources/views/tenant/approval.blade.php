@extends('layouts.app')

@section('title', 'Status Pendaftaran')

@php
    $rejected = $user->status === \App\Enums\UserStatus::Rejected;
@endphp

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        {{-- Kepala kartu: navy untuk pending, merah untuk ditolak --}}
        <div class="{{ $rejected ? 'bg-red-600' : 'bg-blue-950' }} px-8 py-10 text-white">
            <p class="text-xs font-semibold uppercase tracking-widest {{ $rejected ? 'text-red-100' : 'text-blue-300' }}">
                Status pendaftaran
            </p>
            <h1 class="mt-3 text-2xl font-bold">
                @if ($rejected)
                    Pendaftaran Anda ditolak
                @else
                    Menunggu persetujuan admin
                @endif
            </h1>
            <p class="mt-2 text-sm {{ $rejected ? 'text-red-50' : 'text-blue-100/80' }}">
                @if ($rejected)
                    Admin tidak dapat memverifikasi data Anda, sehingga akun ini tidak bisa dipakai untuk menyewa garasi.
                @else
                    Pendaftaran Anda sedang menunggu persetujuan admin. Admin akan mencocokkan NIK dengan KTP Anda.
                    Silakan login lagi nanti untuk melihat hasilnya.
                @endif
            </p>
        </div>

        {{-- Data yang didaftarkan --}}
        <dl class="divide-y divide-slate-100 px-8 py-4 text-sm">
            <div class="flex justify-between py-3">
                <dt class="text-slate-500">Status</dt>
                <dd>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $rejected ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}">
                        {{ $user->status->label() }}
                    </span>
                </dd>
            </div>
            <div class="flex justify-between py-3">
                <dt class="text-slate-500">NIK</dt>
                <dd class="font-medium text-slate-900">{{ $user->nik }}</dd>
            </div>
            <div class="flex justify-between py-3">
                <dt class="text-slate-500">Nama</dt>
                <dd class="font-medium text-slate-900">{{ $user->name }}</dd>
            </div>
            <div class="flex justify-between py-3">
                <dt class="text-slate-500">Email</dt>
                <dd class="font-medium text-slate-900">{{ $user->email }}</dd>
            </div>
            <div class="flex justify-between py-3">
                <dt class="text-slate-500">Tanggal daftar</dt>
                <dd class="font-medium text-slate-900">{{ $user->created_at->format('d-m-Y') }}</dd>
            </div>
            @if ($rejected && $user->verified_at)
                <div class="flex justify-between py-3">
                    <dt class="text-slate-500">Tanggal ditolak</dt>
                    <dd class="font-medium text-slate-900">{{ $user->verified_at->format('d-m-Y') }}</dd>
                </div>
            @endif
        </dl>
    </div>
</div>
@endsection