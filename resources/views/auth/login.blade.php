@extends('layouts.app')
 
@section('title', 'Masuk')
 
@section('content')
<div class="mx-auto grid max-w-4xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm md:grid-cols-2">
    {{-- Panel navy --}}
    <div class="hidden flex-col justify-between bg-blue-950 p-10 text-white md:flex">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-blue-300">Garasiin</p>
            <h1 class="mt-4 text-3xl font-bold leading-tight">Parkir aman, sewa garasi tanpa ribet.</h1>
            <p class="mt-4 text-sm text-blue-100/80">
                Garasi sepeda, mobil, dan mobil listrik lengkap dengan isi daya, harian atau bulanan.
            </p>
        </div>
        <p class="text-sm text-blue-200">Belum punya akun?
            <a href="{{ route('register') }}" class="font-semibold text-white underline">Daftar sekarang</a>
        </p>
    </div>
 
    {{-- Form login --}}
    <div class="p-8 sm:p-10">
        <h2 class="text-2xl font-bold text-slate-900">Masuk</h2>
        <p class="mt-1 text-sm text-slate-500">Gunakan email dan password yang sudah didaftarkan.</p>
 
        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
            @csrf
 
            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 @error('email') @enderror">
                {{-- "Email atau password salah." dari LoginController muncul di sini --}}
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
 
            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                <input id="password" name="password" type="password" required
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 @error('password') @enderror">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
 
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-blue-600">
                Ingat saya
            </label>
 
            <button type="submit"
                    class="w-full rounded-full bg-blue-600 py-3 text-sm font-semibold text-white hover:bg-blue-700">
                Masuk
            </button>
        </form>
 
        <p class="mt-6 text-center text-sm text-slate-500 md:hidden">
            Belum punya akun? <a href="{{ route('register') }}" class="font-semibold text-blue-600">Daftar</a>
        </p>
    </div>
</div>
@endsection
 