<?php

namespace App\Settings;

use App\Models\AdminAuditLog;
use App\Models\ProviderSecret;
use App\Models\User;
use InvalidArgumentException;

/**
 * Reads and writes the provider API keys.
 *
 * A key saved in the admin area wins. Otherwise the server configuration or environment is used. The
 * key itself is never returned by the API; callers only learn whether it is set, where it came from,
 * and its last four characters.
 */
class ProviderCredentials
{
    /** The keys this platform uses, with the name shown to the owner. */
    public const NAMES = [
        'ANTHROPIC_API_KEY' => 'Anthropic (Claude text)',
        'FAL_KEY' => 'fal.ai (GPT Image and Seedance)',
        'SMTP_PASSWORD' => 'Email server (SMTP) password',
        'FLUTTERWAVE_SECRET_KEY' => 'Flutterwave secret key',
        'FLUTTERWAVE_SECRET_HASH' => 'Flutterwave webhook secret hash',
        'PADDLE_API_KEY' => 'Paddle API key',
        'PADDLE_WEBHOOK_SECRET' => 'Paddle webhook signing secret',
    ];

    /** Payment provider credentials, managed on the Settings page. */
    public const PAYMENT_NAMES = ['FLUTTERWAVE_SECRET_KEY', 'FLUTTERWAVE_SECRET_HASH', 'PADDLE_API_KEY', 'PADDLE_WEBHOOK_SECRET'];

    /** The AI provider keys, shown on the API keys page. The SMTP password lives under Settings. */
    public const AI_NAMES = ['ANTHROPIC_API_KEY', 'FAL_KEY'];

    public static function known(string $name): bool
    {
        return array_key_exists($name, self::NAMES);
    }

    public static function get(string $name): ?string
    {
        $saved = ProviderSecret::query()->where('name', $name)->first()?->value;

        if (is_string($saved) && $saved !== '') {
            return $saved;
        }

        $configured = config("virgoai.credentials.{$name}") ?: env($name);

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    public static function has(string $name): bool
    {
        return self::get($name) !== null;
    }

    /** database, environment, or missing. */
    public static function source(string $name): string
    {
        if (ProviderSecret::query()->where('name', $name)->exists()) {
            return 'database';
        }

        return self::has($name) ? 'environment' : 'missing';
    }

    public static function set(string $name, string $value, ?User $actor): void
    {
        self::assertKnown($name);

        $value = trim($value);

        if ($value === '' || preg_match('/\s/', $value)) {
            throw new InvalidArgumentException('The key must not be empty or contain spaces.');
        }

        ProviderSecret::query()->updateOrCreate(
            ['name' => $name],
            ['value' => $value, 'last4' => substr($value, -4), 'updated_by' => $actor?->id],
        );

        // The audit records the name only, never the value.
        AdminAuditLog::record($actor, 'credential.saved', null, ['name' => $name]);
    }

    public static function clear(string $name, ?User $actor): void
    {
        self::assertKnown($name);

        ProviderSecret::query()->where('name', $name)->delete();
        AdminAuditLog::record($actor, 'credential.removed', null, ['name' => $name]);
    }

    /** @return array<string, mixed> */
    public static function describe(string $name): array
    {
        $saved = ProviderSecret::query()->where('name', $name)->with('user')->first();
        $source = self::source($name);

        return [
            'name' => $name,
            'label' => self::NAMES[$name] ?? $name,
            'source' => $source,
            'configured' => $source !== 'missing',
            'last4' => $source === 'database' ? $saved?->last4 : (self::has($name) ? substr((string) self::get($name), -4) : null),
            'updated_at' => $saved?->updated_at?->toIso8601String(),
        ];
    }

    private static function assertKnown(string $name): void
    {
        if (! self::known($name)) {
            throw new InvalidArgumentException("[{$name}] is not a key this platform uses.");
        }
    }
}
