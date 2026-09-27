<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function academicTerm(): array
    {
        $automatic = self::automaticAcademicTerm();

        if (! Schema::hasTable('system_settings')) {
            return [...$automatic, 'mode' => 'automatic', 'automatic_label' => $automatic['label']];
        }

        $settings = self::whereIn('key', ['academic_term_mode', 'academic_semester', 'academic_year'])
            ->pluck('value', 'key');
        $mode = $settings->get('academic_term_mode', 'automatic');
        $semester = $settings->get('academic_semester');
        $schoolYear = $settings->get('academic_year');

        if ($mode === 'manual' && $semester && $schoolYear) {
            return [
                'mode' => 'manual',
                'semester' => $semester,
                'school_year' => $schoolYear,
                'label' => strtoupper("{$semester} SY {$schoolYear}"),
                'automatic_label' => $automatic['label'],
            ];
        }

        return [...$automatic, 'mode' => 'automatic', 'automatic_label' => $automatic['label']];
    }

    private static function automaticAcademicTerm(): array
    {
        $today = Carbon::now('Asia/Manila');
        $year = $today->year;

        if ($today->month >= 8) {
            $semester = 'First Semester';
            $schoolYear = "{$year}-".($year + 1);
        } elseif ($today->month <= 5) {
            $semester = 'Second Semester';
            $schoolYear = ($year - 1)."-{$year}";
        } else {
            $semester = 'Midyear Term';
            $schoolYear = ($year - 1)."-{$year}";
        }

        return [
            'semester' => $semester,
            'school_year' => $schoolYear,
            'label' => strtoupper("{$semester} SY {$schoolYear}"),
        ];
    }
}
