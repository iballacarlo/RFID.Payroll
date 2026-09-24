<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeCredentialController;
use App\Http\Controllers\FacultyRankController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::resource('employees', EmployeeController::class)->except(['show'])->middleware('role:admin');
    Route::post('employees/{employee}/credentials/enrollments', [EmployeeCredentialController::class, 'store'])->name('employees.credentials.enrollments.store')->middleware('role:admin');
    Route::get('employees/{employee}/credentials/enrollments/{enrollment}', [EmployeeCredentialController::class, 'show'])->name('employees.credentials.enrollments.show')->middleware('role:admin');
    Route::delete('employees/{employee}/credentials/enrollments/{enrollment}', [EmployeeCredentialController::class, 'destroy'])->name('employees.credentials.enrollments.destroy')->middleware('role:admin');
    Route::get('employees/{employee}/schedule', [EmployeeController::class, 'editSchedule'])->name('employees.schedule.edit')->middleware('role:admin');
    Route::put('employees/{employee}/schedule', [EmployeeController::class, 'updateSchedule'])->name('employees.schedule.update')->middleware('role:admin');
    Route::get('faculty-ranks', [FacultyRankController::class, 'index'])->name('ranks.index')->middleware('role:admin');
    Route::put('faculty-ranks/{rank}', [FacultyRankController::class, 'update'])->name('ranks.update')->middleware('role:admin');
    Route::redirect('settings', '/settings/accounts')->middleware('role:admin');
    Route::resource('settings/accounts', UserController::class)->except(['show'])->parameters(['accounts' => 'user'])->names([
        'index' => 'settings.accounts.index', 'create' => 'settings.accounts.create',
        'store' => 'settings.accounts.store', 'edit' => 'settings.accounts.edit',
        'update' => 'settings.accounts.update', 'destroy' => 'settings.accounts.destroy',
    ])->middleware('role:admin');
    Route::get('settings/backup', [BackupController::class, 'index'])->name('settings.backup.index')->middleware('role:admin');
    Route::get('settings/backup/download', [BackupController::class, 'download'])->name('settings.backup.download')->middleware('role:admin');
    Route::post('settings/backup/restore', [BackupController::class, 'restore'])->name('settings.backup.restore')->middleware('role:admin');

    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('attendance/tap', [AttendanceController::class, 'tap'])->name('attendance.tap')->middleware('role:admin,payroll_staff');
    Route::post('attendance/manual', [AttendanceController::class, 'manual'])->name('attendance.manual')->middleware('role:admin,payroll_staff');
    Route::post('attendance/generate-test', [AttendanceController::class, 'generateScheduledTestAttendance'])->name('attendance.generate-test')->middleware('role:admin,payroll_staff');

    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('payroll/periods', [PayrollController::class, 'storePeriod'])->name('payroll.periods.store')->middleware('role:admin,payroll_staff');
    Route::post('payroll/periods/{period}/generate', [PayrollController::class, 'generate'])->name('payroll.generate')->middleware('role:admin,payroll_staff');
    Route::get('payroll/records/{record}', [PayrollController::class, 'show'])->name('payroll.records.show');
});
