@extends('layouts.app')

@section('title', 'Faculty')
@section('subtitle', 'Manage faculty profiles, RFID cards, and fingerprint references.')

@section('content')
<div class="panel">
    <div class="panel-heading">
        <h2>Faculty Records</h2>
        <a class="button" href="{{ route('employees.create') }}">Add Faculty</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Employee No.</th>
                <th>Name</th>
                <th>RFID</th>
                <th>Fingerprint ID</th>
                <th>Rate</th>
                <th>Schedule</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employees as $employee)
                <tr>
                    <td>{{ $employee->employee_no }}</td>
                    <td>{{ $employee->full_name }}</td>
                    <td>{{ optional($employee->rfidCards->first())->rfid_uid ?? '-' }}</td>
                    <td>{{ optional($employee->fingerprintTemplates->first())->fingerprint_code ?? '-' }}</td>
                    <td>
                        @if ($employee->facultyRank)
                            {{ $employee->facultyRank->name }}<br><small>PHP {{ number_format($employee->facultyRank->rate_amount, 2) }}/{{ $employee->facultyRank->rate_type }}</small>
                        @else
                            PHP {{ number_format($employee->rate_amount, 2) }} / {{ $employee->rate_type }}
                        @endif
                    </td>
                    <td>{{ $employee->schedules->count() }} day(s)/week</td>
                    <td><span class="badge">{{ $employee->status }}</span></td>
                    <td class="actions">
                        <a href="{{ route('employees.edit', $employee) }}">Edit</a>
                        <form method="POST" action="{{ route('employees.destroy', $employee) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No faculty records yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $employees->links() }}
</div>
@endsection
