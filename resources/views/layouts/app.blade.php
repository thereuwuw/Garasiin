<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Token CSRF dipakai oleh fetch() di halaman sewa baru dan perpanjangan --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Garasiin') · Garasiin</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col bg-slate-100 text-slate-800 antialiased">
    <x-navbar />

    <main class="flex-1 w-full max-w-6xl mx-auto px-4 py-8">
        {{-- Pesan sukses dari ->with('success', ...) di controller --}}
        @if (session('success'))
            <div class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-800">
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-xs text-white">✓</span>
                <p class="text-sm font-medium">{{ session('success') }}</p>
            </div>
        @endif

        @yield('content')
    </main>

    <x-footer />

    @stack('scripts')
</body>
</html>