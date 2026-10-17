<?php

namespace App\Email;

use App\Models\EmailTemplate;
use App\Settings\SystemSettings;

/**
 * Fills {{placeholders}} in a template. Unknown placeholders are left as written, so a typo is visible.
 */
class TemplateRenderer
{
    /**
     * @param  array<string, string|int|float>  $data
     * @return array{subject: string, body: string}
     */
    public function render(EmailTemplate $template, array $data): array
    {
        $values = [
            'app' => config('app.name', 'virgoAi'),
            'support' => SystemSettings::supportEmail(),
            ...array_map(fn ($value) => (string) $value, $data),
        ];

        $replace = fn (string $text) => preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $match) => $values[$match[1]] ?? $match[0],
            $text,
        );

        return [
            'subject' => (string) $replace($template->subject),
            'body' => (string) $replace($template->body),
        ];
    }
}
