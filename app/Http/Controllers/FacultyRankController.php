<?php

namespace App\Http\Controllers;

use App\Models\FacultyRank;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FacultyRankController extends Controller
{
    private const OFFICIAL_RANKS = [
        'Instructor I' => ['salary_grade' => 'SG 12', 'monthly_salary' => 33947.00],
        'Instructor II' => ['salary_grade' => 'SG 13', 'monthly_salary' => 36125.00],
        'Instructor III' => ['salary_grade' => 'SG 14', 'monthly_salary' => 38764.00],
        'Assistant Professor I' => ['salary_grade' => 'SG 15', 'monthly_salary' => 42178.00],
        'Assistant Professor II' => ['salary_grade' => 'SG 16', 'monthly_salary' => 45740.00],
        'Assistant Professor III' => ['salary_grade' => 'SG 17', 'monthly_salary' => 49648.00],
        'Assistant Professor IV' => ['salary_grade' => 'SG 18', 'monthly_salary' => 53922.00],
        'Associate Professor I' => ['salary_grade' => 'SG 19', 'monthly_salary' => 59357.00],
        'Associate Professor II' => ['salary_grade' => 'SG 20', 'monthly_salary' => 66130.00],
        'Associate Professor III' => ['salary_grade' => 'SG 21', 'monthly_salary' => 73677.00],
        'Associate Professor IV' => ['salary_grade' => 'SG 22', 'monthly_salary' => 82075.00],
        'Associate Professor V' => ['salary_grade' => 'SG 23', 'monthly_salary' => 92569.00],
        'Professor I' => ['salary_grade' => 'SG 24', 'monthly_salary' => 104253.00],
        'Professor II' => ['salary_grade' => 'SG 25', 'monthly_salary' => 118621.00],
        'Professor III' => ['salary_grade' => 'SG 26', 'monthly_salary' => 135027.00],
        'Professor IV' => ['salary_grade' => 'SG 27', 'monthly_salary' => 153610.00],
        'Professor V' => ['salary_grade' => 'SG 28', 'monthly_salary' => 174896.00],
        'Professor VI' => ['salary_grade' => 'SG 29', 'monthly_salary' => 194846.00],
        'College/University Professor' => ['salary_grade' => 'SG 30', 'monthly_salary' => 210718.00],
    ];

    public function index()
    {
        $ranks = FacultyRank::withCount('employees')
            ->whereNotNull('salary_grade')
            ->get()
            ->sortBy(fn (FacultyRank $rank) => array_search($rank->name, array_keys(self::OFFICIAL_RANKS), true))
            ->values();

        return Inertia::render('Ranks/Index', [
            'ranks' => $ranks,
        ]);
    }

    public function update(Request $request, FacultyRank $rank)
    {
        $rank->update(['rate_type' => 'hourly', 'rate_amount' => $request->validate([
            'rate_amount' => ['required', 'numeric', 'min:0'],
        ])['rate_amount']]);

        return back()->with('success', 'Faculty rank updated.');
    }
}
