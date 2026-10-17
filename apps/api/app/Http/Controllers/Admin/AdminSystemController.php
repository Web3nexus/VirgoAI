<?php

namespace App\Http\Controllers\Admin;

use App\Ai\AdapterRegistry;
use App\Billing\PricingSettings;
use App\Email\MailConfigurator;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Settings\ProviderCredentials;
use App\Settings\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class AdminSystemController extends Controller
{
    public function show(AdapterRegistry $registry, PricingSettings $pricing): JsonResponse
    {
        return response()->json([
            'settings' => SystemSettings::all(),
            'runtime' => [
                'queue_connection' => config('queue.default'),
                'mail_mailer' => config('mail.default'),
                'margin_percent' => $pricing->marginPercent(),
                'anthropic_ready' => $registry->has('anthropic') && $registry->resolve('anthropic')->isConfigured(),
                'fal_ready' => $registry->has('fal') && $registry->resolve('fal')->isConfigured(),
                'generation_max_wait_seconds' => (int) config('virgoai.generation.max_wait_seconds'),
                'fal_poll_interval_seconds' => (int) config('virgoai.fal.poll_interval_seconds'),
                'anthropic_max_tokens' => (int) config('virgoai.anthropic.max_tokens'),
                'app_env' => app()->environment(),
            ],
            'mail_password' => ProviderCredentials::describe('SMTP_PASSWORD'),
        ]);
    }

    /**
     * Send a real test message with the engine as currently configured. The result is the mailer's own answer.
     */
    public function testEmail(Request $request, MailConfigurator $mail): JsonResponse
    {
        $mail->apply();
        $to = $request->user()->email;

        try {
            Mail::raw('This is a test from virgoAi. If you can read this, the email engine works.', function ($message) use ($to) {
                $message->to($to)
                    ->from(SystemSettings::fromAddress(), SystemSettings::fromName())
                    ->subject('virgoAi test email');
            });
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'The mail server refused the message: '.$e->getMessage()]);
        }

        AdminAuditLog::record($request->user(), 'system.test_email', null, ['driver' => SystemSettings::mailDriver()]);

        return response()->json([
            'ok' => true,
            'message' => SystemSettings::mailDriver() === 'smtp'
                ? "Sent to {$to} through the email server."
                : 'Written to the log, not delivered. Switch the email engine to SMTP to send for real.',
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'registration_enabled' => ['sometimes', 'boolean'],
            'welcome_credit_usd' => ['sometimes', 'numeric', 'between:0,100'],
            'maintenance_mode' => ['sometimes', 'boolean'],
            'maintenance_message' => ['sometimes', 'string', 'max:255'],
            'email_from_name' => ['sometimes', 'string', 'max:120'],
            'email_from_address' => ['sometimes', 'email', 'max:255'],
            'support_email' => ['sometimes', 'email', 'max:255'],
            'mail_driver' => ['sometimes', 'string', Rule::in(['log', 'smtp'])],
            'mail_host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_port' => ['sometimes', 'integer', 'between:1,65535'],
            'mail_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_encryption' => ['sometimes', 'string', Rule::in(['tls', 'ssl', 'none'])],
            // The password is write-only and goes to the encrypted key store.
            'mail_password' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if (array_key_exists('mail_password', $data)) {
            if (filled($data['mail_password'])) {
                ProviderCredentials::set('SMTP_PASSWORD', $data['mail_password'], $request->user());
            }
            unset($data['mail_password']);
        }

        $before = SystemSettings::all();
        $values = [];
        foreach ($data as $key => $value) {
            $values[$key] = $value;
        }

        SystemSettings::update($values);

        $changes = [];
        foreach ($values as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changes[$key] = ['from' => $before[$key] ?? null, 'to' => $value];
            }
        }

        if ($changes !== []) {
            AdminAuditLog::record($request->user(), 'system.updated', null, $changes);
        }

        return response()->json([
            'settings' => SystemSettings::all(),
            'mail_password' => ProviderCredentials::describe('SMTP_PASSWORD'),
        ]);
    }
}
