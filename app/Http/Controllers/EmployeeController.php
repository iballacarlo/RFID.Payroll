<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FacultyRank;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:employee_no,name,rank,rate,weekly_hours,status'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);
        $sort = $filters['sort'] ?? 'employee_no';
        $direction = $filters['direction'] ?? 'desc';
        $employees = Employee::with(['rfidCards', 'fingerprintTemplates', 'facultyRank', 'schedules'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('employee_no', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('suffix', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        if ($sort === 'name') {
            $employees->orderBy('last_name', $direction)->orderBy('first_name', $direction);
        } elseif ($sort === 'rank' || $sort === 'rate') {
            $employees->leftJoin('faculty_ranks as sorted_ranks', 'employees.faculty_rank_id', '=', 'sorted_ranks.id')
                ->select('employees.*')
                ->orderBy($sort === 'rate' ? 'sorted_ranks.rate_amount' : 'sorted_ranks.name', $direction);
        } elseif ($sort === 'weekly_hours') {
            $scheduleHours = DB::table('faculty_schedules')
                ->selectRaw('employee_id, SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as weekly_minutes')
                ->groupBy('employee_id');

            $employees->leftJoinSub($scheduleHours, 'schedule_totals', function ($join) {
                $join->on('employees.id', '=', 'schedule_totals.employee_id');
            })
                ->select('employees.*')
                ->orderByRaw("COALESCE(schedule_totals.weekly_minutes, 0) {$direction}");
        } elseif ($sort === 'status') {
            $employees->orderBy('status', $direction)->orderBy('last_name');
        } else {
            $employees->orderByRaw("CAST(SUBSTRING_INDEX(employee_no, '-', -1) AS UNSIGNED) {$direction}");
        }

        $employees = $employees
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Employees/Form', [
            'employee' => new Employee(),
            'ranks' => $this->standardRanks(),
            'nextEmployeeNumber' => $this->nextEmployeeNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null, true);
        $data['employee_no'] = $this->nextEmployeeNumber();

        $employee = Employee::create($data);
        $this->saveDevices($request, $employee);
        $this->saveSchedules($request, $employee);

        return redirect()->route('employees.index')->with('success', 'Faculty member added.');
    }

    public function edit(Employee $employee)
    {
        $employee->load(['rfidCards', 'fingerprintTemplates']);

        return Inertia::render('Employees/Form', [
            'employee' => $employee,
            'ranks' => $this->standardRanks(),
            'nextEmployeeNumber' => null,
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $employee->update($this->validated($request, $employee->id));
        $this->saveDevices($request, $employee);
        $this->saveSchedules($request, $employee);

        return redirect()->route('employees.index')->with('success', 'Faculty record updated.');
    }

    public function editSchedule(Employee $employee)
    {
        $employee->load(['schedules', 'scheduleBreaks']);

        return Inertia::render('Employees/Schedule', ['employee' => $employee]);
    }

    public function updateSchedule(Request $request, Employee $employee)
    {
        $this->saveSchedules($request, $employee);

        return redirect()->route('employees.index')->with('success', 'Faculty schedule updated.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Faculty record deleted.');
    }

    private function validated(Request $request, ?int $employeeId = null, bool $isCreating = false): array
    {
        $data = $request->validate([
            'employee_no' => $isCreating
                ? ['nullable']
                : ['required', 'max:30', 'unique:employees,employee_no,'.$employeeId.',id'],
            'first_name' => ['required', 'max:100'],
            'middle_name' => ['nullable', 'max:100'],
            'last_name' => ['required', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => [
                'nullable',
                'email',
                'max:100',
                Rule::unique('employees', 'email')->ignore($employeeId),
            ],
            'contact_no' => ['nullable', 'max:20'],
            'highest_educational_attainment' => ['nullable', 'string', 'max:255'],
            'years_of_service' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'faculty_rank_id' => ['required', 'exists:faculty_ranks,id'],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $rank = FacultyRank::where('is_active', true)
            ->whereNotNull('salary_grade')
            ->findOrFail($data['faculty_rank_id']);
        $data['rate_type'] = 'hourly';
        $data['rate_amount'] = $rank->rate_amount;
        $data['position'] = 'COS Faculty Member';
        $data['department'] = 'Department of Computer Studies';
        $data['employment_type'] = 'Contract of Service';

        return $data;
    }

    private function saveDevices(Request $request, Employee $employee): void
    {
        $request->validate([
            'rfid_uid' => [
                'nullable',
                'max:100',
                Rule::unique('rfid_cards', 'rfid_uid')->ignore(optional($employee->rfidCards()->first())->id),
            ],
            'fingerprint_code' => [
                'nullable',
                'max:100',
                Rule::unique('fingerprint_templates', 'fingerprint_code')->ignore(optional($employee->fingerprintTemplates()->first())->id),
            ],
        ]);

        if ($request->filled('rfid_uid')) {
            $employee->rfidCards()->updateOrCreate(
                ['employee_id' => $employee->id],
                ['rfid_uid' => $request->rfid_uid, 'status' => 'active', 'registered_at' => Carbon::now()]
            );
        } else {
            $employee->rfidCards()->delete();
        }

        if ($request->filled('fingerprint_code')) {
            $employee->fingerprintTemplates()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'fingerprint_code' => $request->fingerprint_code,
                    'finger_label' => $request->finger_label ?: 'Primary finger',
                    'status' => 'active',
                    'registered_at' => Carbon::now(),
                ]
            );
        } else {
            $employee->fingerprintTemplates()->delete();
        }
    }

    private function saveSchedules(Request $request, Employee $employee): void
    {
        if (! $request->has('schedule')) {
            return;
        }

        $data = $request->validate([
            'schedule' => ['nullable', 'array'],
            'schedule.*.slots' => ['nullable', 'array'],
            'schedule.*.slots.*' => ['date_format:H:i'],
            'schedule.*.break_slots' => ['nullable', 'array'],
            'schedule.*.break_slots.*' => ['date_format:H:i'],
            'schedule.*.consultation_slots' => ['nullable', 'array'],
            'schedule.*.consultation_slots.*' => ['date_format:H:i'],
        ]);

        $employee->schedules()->delete();
        $employee->scheduleBreaks()->delete();

        foreach ($data['schedule'] ?? [] as $day => $schedule) {
            if (! in_array((int) $day, [1, 2, 3, 4, 5, 6], true)) {
                continue;
            }

            $this->saveSlotBlocks($employee, (int) $day, $schedule['slots'] ?? [], 'class');
            $this->saveSlotBlocks($employee, (int) $day, $schedule['break_slots'] ?? [], 'lunch_break');
            $this->saveSlotBlocks($employee, (int) $day, $schedule['consultation_slots'] ?? [], 'consultation');
        }
    }

    private function saveSlotBlocks(Employee $employee, int $day, array $slotValues, string $type): void
    {
        $slots = collect($slotValues)->unique()->sort()->values();

        if ($slots->isEmpty()) {
            return;
        }

        $start = null;
        $previous = null;

        foreach ($slots as $slot) {
            if ($start === null) {
                $start = $slot;
                $previous = $slot;
                continue;
            }

            $expected = Carbon::createFromFormat('H:i', $previous)->addMinutes(30)->format('H:i');

            if ($slot !== $expected) {
                $this->createScheduleBlock($employee, $day, $start, $previous, $type);
                $start = $slot;
            }

            $previous = $slot;
        }

        $this->createScheduleBlock($employee, $day, $start, $previous, $type);
    }

    private function createScheduleBlock(Employee $employee, int $day, string $start, string $lastSlot, string $type): void
    {
        $end = Carbon::createFromFormat('H:i', $lastSlot)->addMinutes(30)->format('H:i');
        $isLunchBreak = $type === 'lunch_break';

        $relation = $isLunchBreak ? $employee->scheduleBreaks() : $employee->schedules();
        $relation->create([
            'day_of_week' => $day,
            'schedule_type' => $type,
            'start_time' => $start,
            'end_time' => $end,
            ...($isLunchBreak ? [] : ['break_minutes' => 0]),
        ]);
    }

    private function standardRanks()
    {
        return FacultyRank::where('is_active', true)
            ->whereNotNull('salary_grade')
            ->orderBy('monthly_salary')
            ->get();
    }

    private function nextEmployeeNumber(): string
    {
        $usedNumbers = Employee::query()
            ->pluck('employee_no')
            ->map(fn (string $employeeNo) => (int) preg_replace('/\D/', '', $employeeNo))
            ->filter()
            ->all();

        $nextNumber = 1;

        while (in_array($nextNumber, $usedNumbers, true)) {
            $nextNumber++;
        }

        return 'COS-'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
