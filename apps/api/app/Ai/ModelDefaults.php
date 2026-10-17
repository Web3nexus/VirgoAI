<?php

namespace App\Ai;

use App\Ai\Fal\FalEndpoints;
use App\Productions\ProductionService;

/**
 * The model a run uses unless the user picks another. A skill's mode decides the capability it renders
 * (a thumbnail is an image, a short film is a video), and the capability decides the default model.
 */
final class ModelDefaults
{
    /** The default registry key for each capability. */
    private const DEFAULTS = [
        'text' => ProductionService::TEXT_MODEL,
        'image' => FalEndpoints::IMAGE_TEXT,
        'video' => FalEndpoints::VIDEO_IMAGE,
    ];

    /** Which capability each skill mode renders. Audio has no model yet: narration is not connected. */
    private const MODE_CAPABILITY = [
        'full_video' => 'video',
        'image' => 'image',
        'text' => 'text',
        'agent' => 'video',
        'audio' => null,
    ];

    public static function capabilityFor(string $mode): ?string
    {
        return self::MODE_CAPABILITY[$mode] ?? null;
    }

    public static function defaultFor(?string $capability): ?string
    {
        return $capability === null ? null : (self::DEFAULTS[$capability] ?? null);
    }
}
