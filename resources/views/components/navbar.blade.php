@php
    $user = auth()->user();

    // Class untuk link menu: biru kalau sedang aktif
    $link = fn (string $pattern) => request()->routeIs($pattern)
        ? 'rounded-full bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700'
        : 'rounded-full px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900';
@endphp

<nav class="bg-white border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-4">
        {{-- Logo --}}
        <a href="{{ route('home') }}" class="flex items-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-lg font-bold text-white">G</span>
            <span class="text-lg font-bold text-slate-900">Garasiin</span>
        </a>

        <div class="flex flex-wrap items-center gap-1">
            @guest
                <a href="{{ route('login') }}" class="{{ $link('login') }}">Masuk</a>
                <a href="{{ route('register') }}"
                   class="ml-2 rounded-full bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Daftar
                </a>
            @endguest

            @auth
                @if ($user->role === \App\Enums\UserRole::Admin)
                    <a href="{{ route('admin.garages.index') }}" class="{{ $link('admin.garages.*') }}">Daftar Garasi</a>
                    <a href="{{ route('admin.verifications.index') }}" class="{{ $link('admin.verifications.*') }}">Verifikasi Penyewa</a>
                    <a href="{{ route('admin.extensions.index') }}" class="{{ $link('admin.extensions.*') }}">Perpanjangan Sewa</a>
                    <a href="{{ route('admin.reports.index') }}" class="{{ $link('admin.reports.*') }}">Laporan</a>
                @elseif ($user->status !== \App\Enums\UserStatus::Accepted)
                    <a href="{{ route('tenant.approval') }}" class="{{ $link('tenant.approval') }}">Status Pendaftaran</a>
                @endif

                <span class="mx-2 hidden h-6 w-px bg-slate-200 sm:block"></span>
                <span class="hidden text-sm text-slate-500 sm:block">{{ $user->name }}</span>

                <form method="POST" action="{{ route('logout') }}" class="ml-2">
                    @csrf
                    <button type="submit"
                            class="rounded-full bg-blue-950 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-900">
                        Keluar
                    </button>
                </form>
            @endauth
        </div>
    </div>
</nav>