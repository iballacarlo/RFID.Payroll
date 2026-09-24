<?php

use App\Http\Controllers\HardwareAttendanceController;
use Illuminate\Support\Facades\Route;

Route::post('hardware/tap', [HardwareAttendanceController::class, 'tap'])
    ->middleware('throttle:60,1');
Route::get('hardware/enrollment', [HardwareAttendanceController::class, 'pendingEnrollment'])
    ->middleware('throttle:120,1');
Route::post('hardware/enrollments/{enrollment}/result', [HardwareAttendanceController::class, 'completeEnrollment'])
    ->middleware('throttle:30,1');
