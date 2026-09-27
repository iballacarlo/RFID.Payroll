@extends('layouts.app')

@section('title', 'Faculty Ranks')
@section('subtitle', 'Set one hourly rate per rank. Every faculty member with the same rank uses the same rate.')

@section('content')
<div class="panel form-note">Faculty 1 to Faculty 5 are fixed. Update the hourly amount here, then assign the rank from the Faculty Record dropdown.</div>

<div class="panel">
    <div class="panel-heading"><h2>Configured Ranks</h2></div>
    <table>
        <thead><tr><th>Rank</th><th>Hourly Rate</th><th>Faculty Assigned</th><th>Update</th></tr></thead>
        <tbody>
        @forelse ($ranks as $rank)
            <tr>
                    <td><form id="rank-{{ $rank->id }}" method="POST" action="{{ route('ranks.update', $rank) }}">@csrf @method('PUT')<strong>{{ $rank->name }}</strong></form></td>
                    <td class="rank-rate">PHP <input form="rank-{{ $rank->id }}" type="number" step="0.01" min="0" name="rate_amount" value="{{ $rank->rate_amount }}" required> / hour</td>
                    <td>{{ $rank->employees_count }}</td>
                    <td><button form="rank-{{ $rank->id }}" type="submit">Save</button></td>
            </tr>
        @empty
            <tr><td colspan="4">No faculty ranks yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
