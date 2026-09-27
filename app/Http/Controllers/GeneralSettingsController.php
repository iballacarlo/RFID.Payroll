<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GeneralSettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('Settings/General', [
            'academicTerm' => SystemSetting::academicTerm(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:automatic,manual'],
            'semester' => [Rule::requiredIf($request->mode === 'manual'), 'nullable', 'in:First Semester,Second Semester,Midyear Term'],
            'school_year' => [Rule::requiredIf($request->mode === 'manual'), 'nullable', 'regex:/^\d{4}-\d{4}$/'],
        ]);

        if ($data['mode'] === 'manual') {
            [$startYear, $endYear] = array_map('intval', explode('-', $data['school_year']));
            if ($endYear !== $startYear + 1) {
                return back()->withErrors(['school_year' => 'School year must contain consecutive years, such as 2026-2027.']);
            }
        }

        foreach ([
            'academic_term_mode' => $data['mode'],
            'academic_semester' => $data['semester'] ?? null,
            'academic_year' => $data['school_year'] ?? null,
        ] as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('success', 'Academic term settings updated.');
    }
}
