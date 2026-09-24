<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BackupController extends Controller
{
    private const TABLES = [
        'faculty_ranks', 'employees', 'users', 'rfid_cards', 'fingerprint_templates', 'employment_histories',
        'faculty_schedules', 'faculty_schedule_breaks', 'attendance_logs',
        'payroll_periods', 'payroll_records', 'audit_trails',
    ];

    public function index()
    {
        return Inertia::render('Settings/Backup');
    }

    public function download()
    {
        $backup = [
            'application' => 'CvSU Payroll System',
            'version' => 1,
            'created_at' => now()->toIso8601String(),
            'tables' => collect(self::TABLES)->mapWithKeys(
                fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()]
            )->all(),
        ];

        $filename = 'payroll-backup-'.now()->format('Y-m-d-His').'.json';

        return response()->streamDownload(function () use ($backup) {
            echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function restore(Request $request)
    {
        $request->validate(['backup_file' => ['required', 'file', 'max:20480']]);
        $backup = json_decode($request->file('backup_file')->get(), true);

        if (! is_array($backup) || ($backup['application'] ?? null) !== 'CvSU Payroll System' || ($backup['version'] ?? null) !== 1) {
            throw ValidationException::withMessages(['backup_file' => 'The selected file is not a valid payroll system backup.']);
        }

        foreach (array_diff(self::TABLES, ['employment_histories']) as $table) {
            if (! isset($backup['tables'][$table]) || ! is_array($backup['tables'][$table])) {
                throw ValidationException::withMessages(['backup_file' => "Backup data for {$table} is missing."]);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::transaction(function () use ($backup) {
                foreach (array_reverse(self::TABLES) as $table) {
                    DB::table($table)->delete();
                }
                foreach (self::TABLES as $table) {
                    foreach (array_chunk($backup['tables'][$table] ?? [], 200) as $rows) {
                        if ($rows) {
                            DB::table($table)->insert($rows);
                        }
                    }
                }
            });
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return redirect()->route('settings.backup.index')->with('success', 'Backup restored successfully.');
    }
}
