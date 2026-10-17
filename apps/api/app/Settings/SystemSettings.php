<?php

namespace App\Settings;

use App\Models\AppSetting;

/**
 * Platform switches the owner changes in the admin area. Each has a safe default, so a fresh install works.
 */
class SystemSettings
{
    public const REGISTRATION = 'registration_enabled';

    public const WELCOME_CREDIT = 'welcome_credit_usd';

    public const MAINTENANCE = 'maintenance_mode';

    public const MAINTENANCE_MESSAGE = 'maintenance_message';

    public const FROM_NAME = 'email_from_name';

    public const FROM_ADDRESS = 'email_from_address';

    public const SUPPORT_EMAIL = 'support_email';

    public const MAIL_DRIVER = 'mail_driver';

    public const MAIL_HOST = 'mail_host';

    public const MAIL_PORT = 'mail_port';

    public const MAIL_USERNAME = 'mail_username';

    public const MAIL_ENCRYPTION = 'mail_encryption';

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return [
            self::REGISTRATION => self::registrationEnabled(),
            self::WELCOME_CREDIT => self::welcomeCreditUsd(),
            self::MAINTENANCE => self::maintenanceOn(),
            self::MAINTENANCE_MESSAGE => self::maintenanceMessage(),
            self::FROM_NAME => self::fromName(),
            self::FROM_ADDRESS => self::fromAddress(),
            self::SUPPORT_EMAIL => self::supportEmail(),
            self::MAIL_DRIVER => self::mailDriver(),
            self::MAIL_HOST => self::mailHost(),
            self::MAIL_PORT => self::mailPort(),
            self::MAIL_USERNAME => self::mailUsername(),
            self::MAIL_ENCRYPTION => self::mailEncryption(),
        ];
    }

    /** log writes emails to the log (nothing is delivered). smtp delivers through the server below. */
    public static function mailDriver(): string
    {
        return (string) AppSetting::read(self::MAIL_DRIVER, 'log');
    }

    public static function mailHost(): string
    {
        return (string) AppSetting::read(self::MAIL_HOST, '');
    }

    public static function mailPort(): int
    {
        return (int) AppSetting::read(self::MAIL_PORT, 587);
    }

    public static function mailUsername(): string
    {
        return (string) AppSetting::read(self::MAIL_USERNAME, '');
    }

    /** tls, ssl, or none. */
    public static function mailEncryption(): string
    {
        return (string) AppSetting::read(self::MAIL_ENCRYPTION, 'tls');
    }

    public static function registrationEnabled(): bool
    {
        return (bool) AppSetting::read(self::REGISTRATION, true);
    }

    public static function welcomeCreditUsd(): float
    {
        return (float) AppSetting::read(self::WELCOME_CREDIT, 0);
    }

    public static function maintenanceOn(): bool
    {
        return (bool) AppSetting::read(self::MAINTENANCE, false);
    }

    public static function maintenanceMessage(): string
    {
        return (string) AppSetting::read(self::MAINTENANCE_MESSAGE, 'virgoAi is down for maintenance. Try again shortly.');
    }

    public static function fromName(): string
    {
        return (string) AppSetting::read(self::FROM_NAME, config('mail.from.name', 'virgoAi'));
    }

    public static function fromAddress(): string
    {
        return (string) AppSetting::read(self::FROM_ADDRESS, config('mail.from.address', 'hello@example.com'));
    }

    public static function supportEmail(): string
    {
        return (string) AppSetting::read(self::SUPPORT_EMAIL, self::fromAddress());
    }

    /** @param  array<string, mixed>  $values */
    public static function update(array $values): void
    {
        foreach ($values as $key => $value) {
            AppSetting::write($key, $value);
        }
    }
}
