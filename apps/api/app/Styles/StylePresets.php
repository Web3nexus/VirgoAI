<?php

namespace App\Styles;

/**
 * The menus a user picks from before generating: where it happens, who is on screen, and how it looks.
 * Each choice becomes plain words added to the prompt the provider receives, so the model gets exactly what
 * the menu says, and the stored run records the choice.
 */
final class StylePresets
{
    public const GROUPS = [
        'environment' => [
            'village' => 'a quiet village with cobbled lanes and small stone houses',
            'city' => 'a busy modern city street with traffic and tall buildings',
            'scifi' => 'a futuristic sci-fi setting with neon lights and advanced technology',
            'fantasy_forest' => 'an enchanted forest with glowing plants and ancient trees',
            'desert' => 'a wide desert under a hot sun',
            'underwater' => 'a clear underwater scene with coral and light rays',
            'space' => 'outer space, a space station with stars and planets',
            'beach' => 'a sunny beach with waves and soft sand',
            'mountains' => 'snowy mountains with a clear wide sky',
            'medieval' => 'a medieval castle courtyard with stone walls and torches',
            'office' => 'a modern office interior with glass and clean lines',
            'farm' => 'a countryside farm with fields, barns and open skies',
        ],
        'character' => [
            'human_realistic' => 'photorealistic real human characters with natural skin, hair and expressions',
            'anime' => 'anime characters with expressive eyes and clean cel-shaded line art',
            '3d' => '3D animated characters with smooth stylised modelling and soft lighting',
            'cartoon' => 'cartoon characters with bold outlines and exaggerated, friendly features',
            'claymation' => 'claymation characters made of clay, with visible handmade texture',
            'comic' => 'comic-book characters with inked outlines and halftone shading',
            'pixel' => 'pixel-art characters on a retro 16-bit style',
        ],
        'look' => [
            'cinematic' => 'cinematic composition, shallow depth of field, film grain, dramatic lighting',
            'documentary' => 'natural documentary look, handheld feel, honest available light',
            'animated' => 'a hand-drawn animated look with clean motion and bright colours',
            'painterly' => 'a painterly look with brush texture and soft colour blending',
            'noir' => 'high-contrast black and white noir with hard shadows',
            'neon' => 'a neon-lit look with saturated magenta and cyan highlights',
            'pastel' => 'a soft pastel palette with gentle, airy light',
        ],
    ];

    /** @return list<string> */
    public static function keys(string $group): array
    {
        return array_keys(self::GROUPS[$group] ?? []);
    }

    /**
     * The chosen styles as a sentence the provider reads. Unknown or empty choices are ignored.
     *
     * @param  array<string, mixed>  $styles
     */
    public static function directive(array $styles): string
    {
        $parts = [];

        foreach (self::GROUPS as $group => $options) {
            $choice = $styles[$group] ?? null;
            if (is_string($choice) && isset($options[$choice])) {
                $parts[] = $options[$choice];
            }
        }

        return $parts === [] ? '' : 'Style: '.implode('; ', $parts).'.';
    }

    /**
     * @param  array<string, mixed>  $styles
     */
    public static function applyToPrompt(string $prompt, array $styles): string
    {
        $directive = self::directive($styles);

        return $directive === '' ? $prompt : trim($prompt)."\n\n".$directive;
    }

    /** @return array{groups: array<string, array<string, string>>} */
    public static function catalog(): array
    {
        return ['groups' => collect(self::GROUPS)->map(fn (array $options) => collect($options)->map(fn (string $text, string $key) => ['key' => $key, 'label' => ucwords(str_replace('_', ' ', $key)), 'description' => $text])->values())->all()];
    }
}
