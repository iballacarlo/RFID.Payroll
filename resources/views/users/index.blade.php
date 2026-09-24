@extends('layouts.app')

@section('title', 'User Accounts')
@section('subtitle', 'Create and manage role-based access for administrators, payroll staff, and faculty.')

@section('content')
<div class="panel">
    <div class="panel-heading">
        <h2>System Users</h2>
        <a class="button" href="{{ route('users.create') }}">Add Account</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Linked Faculty</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td><span class="badge">{{ str_replace('_', ' ', $user->role) }}</span></td>
                    <td>{{ $user->employee?->full_name ?? '-' }}</td>
                    <td class="actions">
                        <a href="{{ route('users.edit', $user) }}">Edit</a>
                        @if (auth()->id() !== $user->id)
                            <form method="POST" action="{{ route('users.destroy', $user) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No user accounts yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $users->links() }}
</div>
@endsection
