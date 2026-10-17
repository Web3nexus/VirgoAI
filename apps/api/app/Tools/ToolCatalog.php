<?php

namespace App\Tools;

use App\Enums\Capability;

/**
 * The studio tools a user can run by hand. Each tool maps to one generation capability and
 * an output type. Models are chosen per run from the registry; this file only describes tools.
 */
final class ToolCatalog
{
    /**
     * @var array<string, array{label: string, description: string, capability: Capability, output: string, sizes: bool, default_size: string, choices?: array<string, array{label: string, default: string, values: array<string, string>}>}>
     */
    public const TOOLS = [
        'story' => [
            'label' => 'Story',
            'description' => 'Turn an idea into a production script: characters, places, scenes, and narration or dialogue.',
            'capability' => Capability::Text,
            'output' => 'text',
            'sizes' => false,
            'default_size' => 'landscape_16_9',
            'choices' => [
                'script_type' => [
                    'label' => 'Script type',
                    'default' => 'story',
                    'values' => [
                        'story' => 'Story (narration)',
                        'dialogue' => 'Dialogue (characters speak)',
                    ],
                ],
                'skill' => [
                    'label' => 'Writing skill',
                    'default' => 'director',
                    'values' => [
                        'director' => 'Director (production script)',
                        'plain' => 'Plain (free text)',
                    ],
                ],
            ],
        ],
        'image' => [
            'label' => 'Image',
            'description' => 'Generate a still image from a prompt.',
            'capability' => Capability::Image,
            'output' => 'image',
            'sizes' => true,
            'default_size' => 'landscape_16_9',
        ],
        'character' => [
            'label' => 'Character',
            'description' => 'Generate a character reference image from an identity description.',
            'capability' => Capability::Image,
            'output' => 'image',
            'sizes' => true,
            'default_size' => 'portrait_4_5',
        ],
        'thumbnail' => [
            'label' => 'Thumbnail',
            'description' => 'Generate the base art for a thumbnail. Overlay text is added later.',
            'capability' => Capability::Image,
            'output' => 'image',
            'sizes' => true,
            'default_size' => 'landscape_16_9',
        ],
        'video' => [
            'label' => 'Video',
            'description' => 'Generate a short motion clip from a prompt or keyframe.',
            'capability' => Capability::Video,
            'output' => 'video',
            'sizes' => true,
            'default_size' => 'landscape_16_9',
        ],
        'narration' => [
            'label' => 'Narration',
            'description' => 'Speak a script with a chosen voice.',
            'capability' => Capability::Audio,
            'output' => 'audio',
            'sizes' => false,
            'default_size' => 'landscape_16_9',
        ],
    ];

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::TOOLS);
    }

    /**
     * @return array{label: string, description: string, capability: Capability, output: string, sizes: bool, default_size: string}
     */
    public static function get(string $key): array
    {
        return self::TOOLS[$key];
    }
}
