@extends('layouts.app')

@section('title', 'Payslip')
@section('subtitle', 'Printable faculty payroll summary.')

@section('content')
<section class="payslip">
    <div class="payslip-header">
        <div class="payslip-title">
            <div class="payslip-logos">
                <img src="{{ asset('images/cvsu-logo.png') }}" alt="Cavite State University logo">
                <img src="{{ asset('images/dcs-logo.png') }}" alt="Department of Computer Studies logo">
            </div>
            <div>
                <h2>Cavite State University - Imus Campus</h2>
                <p>Department of Computer Studies</p>
            </div>
        </div>
        <button onclick="window.print()">Print</button>
    </div>

    <div class="payslip-grid">
        <span>Faculty Member</span><strong>{{ $record->employee->full_name }}</strong>
        <span>Employee No.</span><strong>{{ $record->employee->employee_no }}</strong>
        <span>Payroll Period</span><strong>{{ $record->payrollPeriod->period_name }}</strong>
        <span>Coverage</span><strong>{{ $record->payrollPeriod->start_date }} to {{ $record->payrollPeriod->end_date }}</strong>
        <span>Days Worked</span><strong>{{ $record->total_days_worked }}</strong>
        <span>Hours Worked</span><strong>{{ $record->total_hours_worked }}</strong>
        <span>Gross Pay</span><strong>PHP {{ number_format($record->gross_pay, 2) }}</strong>
        <span>Deductions</span><strong>PHP {{ number_format($record->total_deductions, 2) }}</strong>
        <span>Adjustments</span><strong>PHP {{ number_format($record->total_adjustments, 2) }}</strong>
        <span>Net Pay</span><strong class="net">PHP {{ number_format($record->net_pay, 2) }}</strong>
    </div>
</section>
@endsection
