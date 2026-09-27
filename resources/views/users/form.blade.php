@extends('layouts.app')

@section('title', $user->exists ? 'Edit Account' : 'Add Account')
@section('subtitle', 'Assign the correct role so each user only sees the modules they need.')

@section('content')
<form class="panel form-grid" method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
    @csrf
    @if ($user->exists)
        @method('PUT')
    @endif

    <label>Name<input name="name" value="{{ old('name', $user->name) }}" required></label>
    <label>Email<input type="email" name="email" value="{{ old('email', $user->email) }}" required></label>
    <label>Password<input type="password" name="password" placeholder="{{ $user->exists ? 'Leave blank to keep current password' : 'Minimum 6 characters' }}" {{ $user->exists ? '' : 'required' }}></label>

    <label>Role
        <select name="role" required>
            <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
            <option value="payroll_staff" @selected(old('role', $user->role) === 'payroll_staff')>Payroll Staff</option>
            <option value="faculty" @selected(old('role', $user->role) === 'faculty')>Faculty</option>
        </select>
    </label>

    <label>Linked Faculty
        <select name="employee_id">
            <option value="">None</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" @selected((string) old('employee_id', $user->employee_id) === (string) $employee->id)>
                    {{ $employee->full_name }} - {{ $employee->employee_no }}
                </option>
            @endforeach
        </select>
    </label>

    <div class="form-note">
        Faculty accounts must be linked to a faculty record. Admin and payroll staff accounts do not need a linked faculty profile.
    </div>

    <div class="form-actions">
        <a href="{{ route('users.index') }}">Cancel</a>
        <button class="button" type="submit">Save Account</button>
    </div>
</form>
@endsection
