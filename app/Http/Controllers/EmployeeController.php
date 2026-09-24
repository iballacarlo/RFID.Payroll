<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FacultyRank;
use App\Models\User;
use App\Services\GoogleAppsScriptMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $employees = Employee::with(['rfidCards', 'fingerprintTemplates', 'facultyRank', 'schedules', 'user'])
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
        } elseif ($sort === 'rank') {
            $employees->leftJoin('faculty_ranks as sorted_ranks', 'employees.faculty_rank_id', '=', 'sorted_ranks.id')
                ->select('employees.*')
                ->orderBy('sorted_ranks.name', $direction);
        } elseif ($sort === 'rate') {
            $employees->orderBy('rate_amount', $direction);
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
        $today = Carbon::today();
        $employees->getCollection()->each(function (Employee $employee) use ($today) {
            $daysRemaining = $employee->contract_end
                ? $today->diffInDays(Carbon::parse($employee->contract_end), false)
                : null;
            $employee->setAttribute('contract_days_remaining', $daysRemaining !== null && $daysRemaining >= 0 && $daysRemaining <= 7 ? $daysRemaining : null);
        });

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
            'employee' => new Employee,
            'ranks' => $this->standardRanks(),
            'nextEmployeeNumber' => $this->nextEmployeeNumber(),
        ]);
    }

    public function store(Request $request, GoogleAppsScriptMailer $mailer)
    {
        $temporaryPassword = Str::random(14);
        $credentials = DB::transaction(function () use ($request, $temporaryPassword) {
            $data = $this->validated($request, null, true);
            $data['employee_no'] = $this->nextEmployeeNumber();

            $employee = Employee::create($data);
            User::create([
                'employee_id' => $employee->id,
                'name' => implode(' ', array_filter([$employee->first_name, $employee->middle_name, $employee->last_name, $employee->suffix])),
                'email' => $employee->email,
                'password' => $temporaryPassword,
                'role' => 'faculty',
                'must_change_password' => true,
            ]);

            return ['employee' => $employee, 'email' => $employee->email, 'password' => $temporaryPassword];
        });

        $verificationSent = $mailer->sendVerification($credentials['employee']->user);

        return redirect(route('employees.edit', $credentials['employee']).'#attendance-identifiers')
            ->with('success', $verificationSent
                ? 'Faculty account created. A verification link was sent to the email address.'
                : 'Faculty account created, but the verification email was not sent. Check the Google mail configuration.')
            ->with('temporary_credentials', [
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ]);
    }

    public function edit(Employee $employee)
    {
        $employee->load(['rfidCards', 'fingerprintTemplates', 'employmentHistories']);

        return Inertia::render('Employees/Form', [
            'employee' => $employee,
            'ranks' => $this->standardRanks(),
            'nextEmployeeNumber' => null,
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        DB::transaction(function () use ($request, $employee) {
            $employee->update($this->validated($request, $employee->id));
            if ($employee->user) {
                $employee->user->update([
                    'name' => implode(' ', array_filter([$employee->first_name, $employee->middle_name, $employee->last_name, $employee->suffix])),
                    'email' => $employee->email,
                ]);
            }
            $this->saveDevices($request, $employee);
            $this->saveSchedules($request, $employee);
            $this->saveEmploymentHistory($request, $employee);
        });

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
        DB::transaction(function () use ($employee) {
            $employee->user?->delete();
            $employee->delete();
        });

        return redirect()->route('employees.index')->with('success', 'Faculty record deleted.');
    }

    private function validated(Request $request, ?int $employeeId = null, bool $isCreating = false): array
    {
        $linkedUserId = $employeeId ? User::where('employee_id', $employeeId)->value('id') : null;
        $data = $request->validate([
            'employee_no' => $isCreating
                ? ['nullable']
                : ['required', 'max:30', 'unique:employees,employee_no,'.$employeeId.',id'],
            'first_name' => ['required', 'max:100'],
            'middle_name' => ['nullable', 'max:100'],
            'last_name' => ['required', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => [
                'required',
                'email:rfc',
                'ends_with:@cvsu.edu.ph',
                'max:100',
                Rule::unique('employees', 'email')->ignore($employeeId),
                Rule::unique('users', 'email')->ignore($linkedUserId),
            ],
            'contact_no' => ['nullable', 'regex:/^\+639\d{9}$/'],
            'highest_educational_attainment' => [$isCreating ? 'nullable' : 'required', Rule::in([
                "Bachelor's Degree",
                'Post-Baccalaureate Certificate or Diploma',
                "Master's Degree Units",
                "Master's Degree",
                'Doctorate Degree Units',
                'Doctorate Degree',
                'Postdoctoral Studies',
            ])],
            'service_start_date' => [$isCreating ? 'nullable' : 'required', 'date', 'before_or_equal:today'],
            'faculty_rank_id' => ['required', 'exists:faculty_ranks,id'],
            'rate_amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'contract_start' => ['nullable', 'required_with:contract_end', 'date'],
            'contract_end' => ['nullable', 'required_with:contract_start', 'date', 'after_or_equal:contract_start'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $data['rate_type'] = 'hourly';
        $data['position'] = 'COS Faculty Member';
        $data['department'] = 'Department of Computer Studies';
        $data['employment_type'] = 'Contract of Service';

        return $data;
    }

    private function saveEmploymentHistory(Request $request, Employee $employee): void
    {
        $data = $request->validate([
            'employment_history' => ['nullable', 'array', 'max:20'],
            'employment_history.*.employer' => ['required', 'string', 'max:150'],
            'employment_history.*.position' => ['required', 'string', 'max:150'],
            'employment_history.*.started_on' => ['required', 'date'],
            'employment_history.*.ended_on' => ['nullable', 'date'],
        ]);

        $employee->employmentHistories()->delete();
        foreach ($data['employment_history'] ?? [] as $history) {
            if ($history['ended_on'] && $history['ended_on'] < $history['started_on']) {
                throw ValidationException::withMessages([
                    'employment_history' => 'Employment end month must be after its start month.',
                ]);
            }
            $employee->employmentHistories()->create($history);
        }
    }

    private function saveDevices(Request $request, Employee $employee): void
    {
        $deviceData = $request->validate([
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
            'finger_label' => ['nullable', 'string', 'max:50'],
            'rfid_reregister' => ['sometimes', 'boolean'],
            'fingerprint_reregister' => ['sometimes', 'boolean'],
        ]);

        if ($request->filled('rfid_uid')) {
            $rfid = $employee->rfidCards()->firstOrNew(['employee_id' => $employee->id]);
            $changed = $rfid->rfid_uid !== $deviceData['rfid_uid'];
            $rfid->fill(['rfid_uid' => $deviceData['rfid_uid'], 'status' => 'active']);
            if (! $rfid->exists || $changed || ($deviceData['rfid_reregister'] ?? false)) {
                $rfid->registered_at = Carbon::now();
            }
            $rfid->save();
        } else {
            $employee->rfidCards()->delete();
        }

        if ($request->filled('fingerprint_code')) {
            $fingerprint = $employee->fingerprintTemplates()->firstOrNew(['employee_id' => $employee->id]);
            $changed = $fingerprint->fingerprint_code !== $deviceData['fingerprint_code'];
            $fingerprint->fill([
                'fingerprint_code' => $deviceData['fingerprint_code'],
                'finger_label' => ($deviceData['finger_label'] ?? null) ?: 'Primary finger',
                'status' => 'active',
            ]);
            if (! $fingerprint->exists || $changed || ($deviceData['fingerprint_reregister'] ?? false)) {
                $fingerprint->registered_at = Carbon::now();
            }
            $fingerprint->save();
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
