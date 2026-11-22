<?php

use App\Http\Controllers\Admin\GarageController;
use App\Http\Controllers\Admin\RentalExtensionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TenantVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Tenant\ApprovalController;
use App\Http\Controllers\Tenant\RentalController as TenantRentalController;
use App\Services\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route(UserService::homeRoute(Auth::user()))
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Penyewa
    Route::middleware('role:penyewa')->prefix('penyewa')->name('tenant.')->group(function () {
        Route::get('/status', [ApprovalController::class, 'show'])->name('approval');

        Route::middleware('accepted')->group(function () {
            Route::get('/sewa', [TenantRentalController::class, 'index'])->name('rentals.index');
            Route::get('/sewa/baru', [TenantRentalController::class, 'create'])->name('rentals.create');
            Route::get('/sewa/garasi-tersedia', [TenantRentalController::class, 'availableGarages'])->name('rentals.garages');
            Route::post('/sewa/preview', [TenantRentalController::class, 'preview'])->name('rentals.preview');
            Route::post('/sewa', [TenantRentalController::class, 'store'])->name('rentals.store');
        });
    });

    // Admin
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/garasi', [GarageController::class, 'index'])->name('garages.index');
        Route::get('/garasi/buat', [GarageController::class, 'create'])->name('garages.create');
        Route::post('/garasi', [GarageController::class, 'store'])->name('garages.store');

        Route::get('/verifikasi', [TenantVerificationController::class, 'index'])->name('verifications.index');
        Route::patch('/verifikasi/{user}/terima', [TenantVerificationController::class, 'accept'])->name('verifications.accept');
        Route::patch('/verifikasi/{user}/tolak', [TenantVerificationController::class, 'reject'])->name('verifications.reject');

        Route::get('/perpanjangan', [RentalExtensionController::class, 'index'])->name('extensions.index');
        Route::get('/perpanjangan/{rental}', [RentalExtensionController::class, 'edit'])->name('extensions.edit');
        Route::post('/perpanjangan/{rental}/preview', [RentalExtensionController::class, 'preview'])->name('extensions.preview');
        Route::post('/perpanjangan/{rental}', [RentalExtensionController::class, 'store'])->name('extensions.store');

        Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
    });
});
