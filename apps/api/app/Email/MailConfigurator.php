<?php

namespace App\Email;

use App\Settings\ProviderCredentials;
use App\Settings\SystemSettings;
use Illuminate\Support\Facades\Mail;

/**
 * Points Laravel's mailer at the engine the owner set in Settings. The SMTP password is read from the
 * encrypted key store, never from the settings table.
 */
class MailConfigurator
{
    public function apply(): void
    {
        if (SystemSettings::mailDriver() !== 'smtp') {
            return;
        }

        $encryption = SystemSettings::mailEncryption();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => SystemSettings::mailHost(),
                'port' => SystemSettings::mailPort(),
                'encryption' => $encryption === 'none' ? null : $encryption,
                'username' => SystemSettings::mailUsername() ?: null,
                'password' => ProviderCredentials::get('SMTP_PASSWORD'),
                'timeout' => 20,
                'local_domain' => null,
            ],
        ]);

        Mail::purge('smtp');
    }

    /** Whether the owner has finished setting up SMTP. */
    public static function smtpReady(): bool
    {
        return SystemSettings::mailDriver() === 'smtp'
            && SystemSettings::mailHost() !== ''
            && ProviderCredentials::has('SMTP_PASSWORD');
    }
}
