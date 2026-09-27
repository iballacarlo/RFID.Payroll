<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Services\GoogleAppsScriptMailer;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, User $user, string $hash)
    {
        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->route('login')->with('success', 'Email address verified. You may now sign in.');
    }

    public function send(Request $request, GoogleAppsScriptMailer $mailer)
    {
        $sent = $mailer->sendVerification($request->user());

        return back()->with(
            $sent ? 'success' : 'error',
            $sent ? 'A new verification link was sent.' : 'The verification email could not be sent. Check the mail service configuration.'
        );
    }

    public function resendForEmployee(Employee $employee, GoogleAppsScriptMailer $mailer)
    {
        abort_unless($employee->user, 404);
        $sent = $mailer->sendVerification($employee->user);

        return back()->with(
            $sent ? 'success' : 'error',
            $sent ? 'Verification email sent to '.$employee->user->email.'.' : 'The verification email could not be sent.'
        );
    }
}
