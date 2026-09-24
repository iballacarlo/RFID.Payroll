<?php

use App\Http\Controllers\HardwareAttendanceController;
use Illuminate\Support\Facades\Route;

Route::post('hardware/tap', [HardwareAttendanceController::class, 'tap'])
    ->middleware('throttle:60,1');
