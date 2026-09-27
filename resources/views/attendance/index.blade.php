@extends('layouts.app')

@section('title', 'Attendance')
@section('subtitle', 'Record attendance through RFID, fingerprint code, or manual entry.')

@section('content')
@if (auth()->user()->role !== 'faculty')
<section class="content-grid">
    <form class="panel" method="POST" action="{{ route('attendance.tap') }}">
        @csrf
        <h2>RFID / Fingerprint Tap</h2>
        <label>Identifier<input name="identifier" placeholder="Scan RFID UID or fingerprint code" required autofocus></label>
        <label>Method
            <select name="method" required>
                <option value="rfid">RFID</option>
                <option value="fingerprint">Fingerprint</option>
            </select>
        </label>
        <button class="button" type="submit">Record Tap</button>
    </form>

    <form class="panel" method="POST" action="{{ route('attendance.manual') }}">
        @csrf
        <h2>Manual Attendance</h2>
        <label>Faculty
            <select name="employee_id" required>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                @endforeach
            </select>
        </label>
        <label>Date<input type="date" name="attendance_date" value="{{ now()->toDateString() }}" required></label>
        <label>Time In<input type="time" name="time_in"></label>
        <label>Time Out<input type="time" name="time_out"></label>
        <label>Remarks<input name="remarks"></label>
        <button class="button" type="submit">Save Manual Log</button>
    </form>
</section>

<section class="panel test-attendance-panel">
    <div>
        <h2>Generate Schedule-Based Test Attendance</h2>
        <p>For testing payroll only. Creates complete attendance only on the selected faculty member's scheduled days. Existing attendance logs are preserved.</p>
    </div>
    <form class="test-attendance-form" method="POST" action="{{ route('attendance.generate-test') }}">
        @csrf
        <label>Faculty
            <select name="employee_id" required>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                @endforeach
            </select>
        </label>
        <label>Start Date<input type="date" name="start_date" value="{{ now()->startOfWeek()->toDateString() }}" required></label>
        <label>End Date<input type="date" name="end_date" value="{{ now()->endOfWeek()->toDateString() }}" required></label>
        <button class="button" type="submit">Generate Test Logs</button>
    </form>
</section>
@endif

<div class="panel">
    <div class="panel-heading">
        <h2>{{ auth()->user()->role === 'faculty' ? 'My Attendance Logs' : 'Attendance Logs' }}</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Faculty</th>
                <th>Date</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Method</th>
                <th>Hours</th>
                <th>Late</th>
                <th>Undertime</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->employee->full_name }}</td>
                    <td>{{ $log->attendance_date }}</td>
                    <td>{{ $log->time_in ? \Illuminate\Support\Carbon::parse($log->time_in)->format('h:i A') : '-' }}</td>
                    <td>{{ $log->time_out ? \Illuminate\Support\Carbon::parse($log->time_out)->format('h:i A') : '-' }}</td>
                    <td>{{ $log->method_in ?? '-' }} / {{ $log->method_out ?? '-' }}</td>
                    <td>{{ $log->total_hours }}</td>
                    <td>{{ $log->late_minutes }} min</td>
                    <td>{{ $log->undertime_minutes }} min</td>
                    <td><span class="badge">{{ $log->status }}</span></td>
                </tr>
            @empty
                <tr><td colspan="9">No attendance logs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $logs->links() }}
</div>
@endsection
