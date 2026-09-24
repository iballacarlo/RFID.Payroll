@extends('layouts.app')

@section('title', 'Payroll')
@section('subtitle', auth()->user()->role === 'faculty' ? 'View your generated payslips and payroll summary.' : 'Create payroll periods and compute pay from attendance logs.')

@section('content')
@if (auth()->user()->role !== 'faculty')
<section class="content-grid">
    <form class="panel" method="POST" action="{{ route('payroll.periods.store') }}">
        @csrf
        <h2>New Payroll Period</h2>
        <label>Period Name<input name="period_name" placeholder="September 1-15, 2026" required></label>
        <label>Start Date<input type="date" name="start_date" required></label>
        <label>End Date<input type="date" name="end_date" required></label>
        <label>Pay Date<input type="date" name="pay_date"></label>
        <button class="button" type="submit">Create Period</button>
    </form>

    <div class="panel">
        <h2>Payroll Periods</h2>
        <table>
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Coverage</th>
                    <th>Records</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($periods as $period)
                    <tr>
                        <td>{{ $period->period_name }}</td>
                        <td>{{ $period->start_date }} to {{ $period->end_date }}</td>
                        <td>{{ $period->records_count }}</td>
                        <td><span class="badge">{{ $period->status }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('payroll.generate', $period) }}">
                                @csrf
                                <button type="submit">Generate</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">No payroll periods yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endif

<div class="panel">
    <div class="panel-heading"><h2>{{ auth()->user()->role === 'faculty' ? 'My Payroll Records' : 'Payroll Records' }}</h2></div>
    <table>
        <thead>
            <tr>
                <th>Faculty</th>
                <th>Period</th>
                <th>Days</th>
                <th>Hours</th>
                <th>Gross</th>
                <th>Deductions</th>
                <th>Net Pay</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td>{{ $record->employee->full_name }}</td>
                    <td>{{ $record->payrollPeriod->period_name }}</td>
                    <td>{{ $record->total_days_worked }}</td>
                    <td>{{ $record->total_hours_worked }}</td>
                    <td>PHP {{ number_format($record->gross_pay, 2) }}</td>
                    <td>PHP {{ number_format($record->total_deductions, 2) }}</td>
                    <td><strong>PHP {{ number_format($record->net_pay, 2) }}</strong></td>
                    <td><a href="{{ route('payroll.records.show', $record) }}">Payslip</a></td>
                </tr>
            @empty
                <tr><td colspan="8">No generated payroll yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $records->links() }}
</div>
@endsection
