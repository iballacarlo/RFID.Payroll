@extends('layouts.app')

@section('title', $employee->exists ? 'Edit Faculty' : 'Add Faculty')
@section('subtitle', 'Register faculty information and device identifiers.')

@section('content')
@php
    $rfid = optional($employee->rfidCards->first())->rfid_uid;
    $fingerprint = optional($employee->fingerprintTemplates->first());
    $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
    $savedSchedules = $employee->schedules->keyBy('day_of_week');
@endphp

<form class="panel form-grid" method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}">
    @csrf
    @if ($employee->exists)
        @method('PUT')
    @endif

    <label>Employee Number<input name="employee_no" value="{{ old('employee_no', $employee->employee_no) }}" required></label>
    <label>First Name<input name="first_name" value="{{ old('first_name', $employee->first_name) }}" required></label>
    <label>Middle Name<input name="middle_name" value="{{ old('middle_name', $employee->middle_name) }}"></label>
    <label>Last Name<input name="last_name" value="{{ old('last_name', $employee->last_name) }}" required></label>
    <label>Email<input type="email" name="email" value="{{ old('email', $employee->email) }}"></label>
    <label>Contact Number<input name="contact_no" value="{{ old('contact_no', $employee->contact_no) }}"></label>
    <label>Faculty Rank
        <select name="faculty_rank_id" required>
            <option value="">Select rank</option>
            @foreach ($ranks as $rank)
                <option value="{{ $rank->id }}" @selected((string) old('faculty_rank_id', $employee->faculty_rank_id) === (string) $rank->id)>{{ $rank->name }} - PHP {{ number_format($rank->rate_amount, 2) }}/hour</option>
            @endforeach
        </select>
    </label>
    <label>Status
        <select name="status" required>
            <option value="active" @selected(old('status', $employee->status) === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $employee->status) === 'inactive')>Inactive</option>
        </select>
    </label>
    <label>Contract Start<input type="date" name="contract_start" value="{{ old('contract_start', $employee->contract_start) }}"></label>
    <label>Contract End<input type="date" name="contract_end" value="{{ old('contract_end', $employee->contract_end) }}"></label>
    <label>RFID UID<input name="rfid_uid" value="{{ old('rfid_uid', $rfid) }}"></label>
    <label>Fingerprint ID<input name="fingerprint_code" value="{{ old('fingerprint_code', optional($fingerprint)->fingerprint_code) }}" placeholder="Example: FP-1"></label>
    <label>Finger Label<input name="finger_label" value="{{ old('finger_label', optional($fingerprint)->finger_label) }}"></label>

    <section class="schedule-section">
        <div>
            <h2>Weekly Teaching Schedule</h2>
            <p>Only time inside these shifts is counted for attendance and payroll. Leave a day unchecked when the faculty member has no class/work schedule.</p>
        </div>
        <div class="schedule-table">
            <div class="schedule-head"><span>Day</span><span>Scheduled</span><span>Start</span><span>End</span><span>Break (min)</span></div>
            @foreach ($days as $dayNumber => $dayName)
                @php
                    $saved = $savedSchedules->get($dayNumber);
                    $enabled = old("schedule.$dayNumber.enabled", $saved ? 1 : 0);
                @endphp
                <div class="schedule-row">
                    <strong>{{ $dayName }}</strong>
                    <label class="check-label"><input type="checkbox" name="schedule[{{ $dayNumber }}][enabled]" value="1" @checked($enabled)> <span>Yes</span></label>
                    <input type="time" name="schedule[{{ $dayNumber }}][start_time]" value="{{ old("schedule.$dayNumber.start_time", $saved ? substr($saved->start_time, 0, 5) : '') }}">
                    <input type="time" name="schedule[{{ $dayNumber }}][end_time]" value="{{ old("schedule.$dayNumber.end_time", $saved ? substr($saved->end_time, 0, 5) : '') }}">
                    <input type="number" min="0" max="480" name="schedule[{{ $dayNumber }}][break_minutes]" value="{{ old("schedule.$dayNumber.break_minutes", $saved->break_minutes ?? 0) }}">
                </div>
            @endforeach
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ route('employees.index') }}">Cancel</a>
        <button class="button" type="submit">Save Faculty</button>
    </div>
</form>
@endsection
