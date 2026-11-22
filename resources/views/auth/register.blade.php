@extends('layouts.app')
 
@section('title', 'Daftar')
 
@php
    $input = 'w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100';
@endphp
 
@section('content')
<div class="mx-auto grid max-w-5xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm md:grid-cols-5">
    {{-- Panel navy --}}
    <div class="hidden flex-col justify-between bg-blue-950 p-10 text-white md:col-span-2 md:flex">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-blue-300">Daftar Penyewa</p>
            <h1 class="mt-4 text-3xl font-bold leading-tight">Satu akun untuk semua kendaraan Anda.</h1>
            <ul class="mt-6 space-y-3 text-sm text-blue-100/80">
                <li class="flex gap-2"><span class="text-blue-300">1.</span> Isi data diri sesuai KTP.</li>
                <li class="flex gap-2"><span class="text-blue-300">2.</span> Admin mencocokkan NIK Anda.</li>
                <li class="flex gap-2"><span class="text-blue-300">3.</span> Setelah diterima, Anda bisa mulai menyewa garasi.</li>
            </ul>
        </div>
        <p class="text-sm text-blue-200">Sudah punya akun?
            <a href="{{ route('login') }}" class="font-semibold text-white underline">Masuk</a>
        </p>
    </div>
 
    {{-- Form daftar --}}
    <div class="p-8 sm:p-10 md:col-span-3">
        <h2 class="text-2xl font-bold text-slate-900">Daftar sebagai penyewa</h2>
        <p class="mt-1 text-sm text-slate-500">Semua kolom wajib diisi.</p>
 
        <form method="POST" action="{{ route('register') }}" class="mt-8 grid gap-5 sm:grid-cols-2">
            @csrf
 
            <div class="sm:col-span-2">
                <label for="nik" class="mb-1 block text-sm font-medium text-slate-700">NIK (16 digit)</label>
                <input id="nik" name="nik" type="text" inputmode="numeric" maxlength="16" pattern="[0-9]{16}"
                       value="{{ old('nik') }}" required autofocus
                       class="{{ $input }} @error('nik') border-red-500 @enderror">
                @error('nik') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
 
            <div class="sm:col-span-2">
                <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Nama lengkap</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required
                       class="{{ $input }} @error('name') border-red-500 @enderror">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
 
            <div>
                <label for="phone" class="mb-1 block text-sm font-medium text-slate-700">Nomor telepon</label>
                <input id="phone" name="phone" type="tel" maxlength="20" value="{{ old('phone') }}" required
                       class="{{ $input }} @error('phone') border-red-500 @enderror">
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
 
            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required
                       class="{{ $input }} @error('email') border-red-500 @enderror">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
 
            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Password (min. 8 karakter)</label>
                <input id="password" name="password" type="password" minlength="8" required
                       class="{{ $input }} @error('password') border-red-500 @enderror">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
 
            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-slate-700">Konfirmasi password</label>
                {{-- Nama harus password_confirmation supaya rule "confirmed" bekerja --}}
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required
                       class="{{ $input }}">
            </div>
 
            <div class="sm:col-span-2">
                <button type="submit"
                        class="w-full rounded-full bg-blue-600 py-3 text-sm font-semibold text-white hover:bg-blue-700">
                    Daftar
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
 