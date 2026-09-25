<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class GoogleAppsScriptMailer
{
    public function sendVerification(User $user): bool
    {
        $endpoint = config('services.google_mail.webhook_url');
        $secret = config('services.google_mail.webhook_secret');

        if (! $endpoint || ! $secret) {
            Log::warning('Google Apps Script email delivery is not configured.', [
                'user_id' => $user->id,
                'webhook_url_configured' => (bool) $endpoint,
                'webhook_secret_configured' => (bool) $secret,
            ]);

            return false;
        }

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(48),
            ['user' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );
        $safeName = e($user->name);
        $safeUrl = e($verificationUrl);

        try {
            $response = Http::asJson()
                ->withOptions(['allow_redirects' => true])
                ->timeout(15)
                ->post($endpoint, [
                    'secret' => $secret,
                    'to' => $user->email,
                    'subject' => 'Verify your CvSU Payroll email address',
                    'text' => "Hello {$user->name},\n\nVerify your email address using this link (valid for 48 hours):\n{$verificationUrl}\n\nIf you did not expect this account, contact the payroll administrator.",
                    'html' => "<p>Hello {$safeName},</p><p>Please verify your email address for the CvSU - Imus Payroll System.</p><p><a href=\"{$safeUrl}\">Verify email address</a></p><p>This link expires in 48 hours. If you did not expect this account, contact the payroll administrator.</p>",
                ]);

            $delivered = $response->successful() && $response->json('ok') === true;

            if (! $delivered) {
                Log::warning('Google Apps Script rejected the email delivery request.', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }

            return $delivered;
        } catch (Throwable $exception) {
            Log::warning('Google Apps Script email delivery failed.', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
