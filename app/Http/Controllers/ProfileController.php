<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user()->load('employee.facultyRank');

        return Inertia::render('Profile/Edit', [
            'profileUser' => $user,
        ]);
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $employee = $user->employee;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'required_with:current_password', 'string', 'min:8', 'confirmed'],
        ];

        if ($employee) {
            $rules = [
                ...$rules,
                'first_name' => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'suffix' => ['nullable', 'string', 'max:20'],
                'email' => [
                    'required',
                    'email:rfc',
                    'ends_with:@cvsu.edu.ph',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id),
                    Rule::unique('employees', 'email')->ignore($employee->id),
                ],
                'contact_no' => ['nullable', 'regex:/^\+639\d{9}$/'],
            ];
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($data, $user, $employee) {
            if ($employee) {
                $employee->update([
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'last_name' => $data['last_name'],
                    'suffix' => $data['suffix'] ?? null,
                    'email' => strtolower($data['email']),
                    'contact_no' => $data['contact_no'] ?? null,
                ]);

                $user->name = implode(' ', array_filter([
                    $data['first_name'],
                    $data['middle_name'] ?? null,
                    $data['last_name'],
                    $data['suffix'] ?? null,
                ]));
            } else {
                $user->name = $data['name'];
            }

            $user->email = strtolower($data['email']);
            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }
            $user->save();
        });

        return redirect()->route('profile.edit')->with('success', 'Your profile has been updated.');
    }
}
