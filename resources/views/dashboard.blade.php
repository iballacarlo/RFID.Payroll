@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', auth()->user()->role === 'faculty' ? 'Your attendance and payroll summary.' : 'Overview of faculty attendance and payroll activity.')

@section('content')
<section class="stats-grid">
    <div class="stat-card">
        <small>{{ auth()->user()->role === 'faculty' ? 'Your Profile' : 'Total Faculty' }}</small>
        <strong>{{ $employeeCount }}</strong>
    </div>
    <div class="stat-card">
        <small>Present Today</small>
        <strong>{{ $presentToday }}</strong>
    </div>
    <div class="stat-card">
        <small>{{ auth()->user()->role === 'faculty' ? 'Payroll Records' : 'Open Payroll Periods' }}</small>
        <strong>{{ $openPeriods }}</strong>
    </div>
</section>

<section class="content-grid">
    <div class="panel">
        <div class="panel-heading">
            <h2>Recent Attendance</h2>
            <a href="{{ route('attendance.index') }}">View all</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Faculty</th>
                    <th>Date</th>
                    <th>In</th>
                    <th>Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentAttendance as $log)
                    <tr>
                        <td>{{ $log->employee->full_name }}</td>
                        <td>{{ $log->attendance_date }}</td>
                        <td>{{ $log->time_in ? \Illuminate\Support\Carbon::parse($log->time_in)->format('h:i A') : '-' }}</td>
                        <td>{{ $log->time_out ? \Illuminate\Support\Carbon::parse($log->time_out)->format('h:i A') : '-' }}</td>
                        <td><span class="badge">{{ $log->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5">No attendance logs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="panel">
        <div class="panel-heading">
            <h2>Latest Payroll</h2>
            <a href="{{ route('payroll.index') }}">Manage</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Faculty</th>
                    <th>Period</th>
                    <th>Net Pay</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($latestPayrolls as $record)
                    <tr>
                        <td>{{ $record->employee->full_name }}</td>
                        <td>{{ $record->payrollPeriod->period_name }}</td>
                        <td>PHP {{ number_format($record->net_pay, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">No payroll records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
