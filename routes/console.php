<?php

use App\Services\GarageService;
use Illuminate\Support\Facades\Schedule;

// Garasi yang sewanya sudah selesai kembali menjadi available setiap hari.
Schedule::call(fn () => GarageService::syncStatuses())->dailyAt('00:05');