<?php

namespace App\Ai\Fal;

use App\Ai\Data\GenerationRequest;
use App\Enums\Capability;
use App\Models\Project;
use InvalidArgumentException;

/**
 * The fal.ai endpoints this app calls, each with its request builder and output reader.
 *
 * Every field name here was checked against fal's published input schema. `fal:sync` re-checks
 * them against the live schema with the owner's key, so a renamed field fails the sync instead of
 * failing a paid generation. Endpoints that are not listed here are never called.
 */
final class FalEndpoints
{
    public const IMAGE_TEXT = 'fal-ai/gpt-image-1.5';

    public const IMAGE_EDIT = 'fal-ai/gpt-image-1.5/edit';

    public const VIDEO_IMAGE = 'bytedance/seedance-2.0/image-to-video';

    /** Speech-to-text for clipping. Internal: it is not offered in the model picker. */
    public const TRANSCRIBE = 'fal-ai/whisper';

    /** Shown in the model picker. The edit endpoint is chosen automatically when a reference image is given. */
    private const SELECTABLE = [self::IMAGE_TEXT, self::VIDEO_IMAGE];

    /** Image size per project format, for GPT Image. Its sizes are fixed, so the closest one is used. */
    private const GPT_IMAGE_SIZES = [
        'landscape_16_9' => '1536x1024',
        'vertical_9_16' => '1024x1536',
        'square_1_1' => '1024x1024',
        'portrait_4_5' => '1024x1536',
    ];

    /** Aspect ratio per project format, for Seedance. 4:5 is not offered, so 3:4 is the closest. */
    private const SEEDANCE_RATIOS = [
        'landscape_16_9' => '16:9',
        'vertical_9_16' => '9:16',
        'square_1_1' => '1:1',
        'portrait_4_5' => '3:4',
    ];

    /**
     * @return array<string, array{capability: Capability, category: string, selectable: bool, fields: list<string>}>
     */
    public static function all(): array
    {
        return [
            self::IMAGE_TEXT => [
                'capability' => Capability::Image,
                'category' => 'text-to-image',
                'selectable' => true,
                'fields' => ['prompt', 'image_size', 'quality', 'num_images', 'output_format'],
            ],
            self::IMAGE_EDIT => [
                'capability' => Capability::Image,
                'category' => 'image-to-image',
                'selectable' => false,
                'fields' => ['prompt', 'image_urls', 'image_size', 'quality', 'num_images', 'output_format'],
            ],
            self::VIDEO_IMAGE => [
                'capability' => Capability::Video,
                'category' => 'image-to-video',
                'selectable' => true,
                'fields' => ['prompt', 'image_url', 'resolution', 'duration', 'aspect_ratio', 'generate_audio'],
            ],
            self::TRANSCRIBE => [
                'capability' => Capability::Audio,
                'category' => 'speech-to-text',
                'selectable' => false,
                'fields' => ['audio_url', 'task', 'chunk_level'],
            ],
        ];
    }

    /** @return list<string> */
    public static function selectableIds(): array
    {
        return self::SELECTABLE;
    }

    /**
     * The endpoint to call for a requested model. A reference image switches GPT Image to its edit endpoint.
     */
    public static function resolve(string $modelKey, array $inputs): ?string
    {
        if ($modelKey === self::IMAGE_TEXT && ! empty($inputs['image_url'])) {
            return self::IMAGE_EDIT;
        }

        return array_key_exists($modelKey, self::all()) ? $modelKey : null;
    }

    /**
     * Build the exact JSON body for an endpoint. Throws when a required input is missing.
     *
     * @return array<string, mixed>
     */
    public static function payload(string $endpointId, GenerationRequest $request): array
    {
        $format = (string) ($request->options['size'] ?? Project::DEFAULT_FORMAT);

        return match ($endpointId) {
            self::IMAGE_TEXT => [
                'prompt' => $request->prompt,
                'image_size' => self::GPT_IMAGE_SIZES[$format] ?? self::GPT_IMAGE_SIZES[Project::DEFAULT_FORMAT],
                'quality' => $request->options['quality'] ?? 'medium',
                'num_images' => 1,
                'output_format' => 'png',
            ],
            self::IMAGE_EDIT => [
                'prompt' => $request->prompt,
                'image_urls' => [self::requireImageUrl($request)],
                'image_size' => self::GPT_IMAGE_SIZES[$format] ?? self::GPT_IMAGE_SIZES[Project::DEFAULT_FORMAT],
                'quality' => $request->options['quality'] ?? 'medium',
                'num_images' => 1,
                'output_format' => 'png',
            ],
            self::VIDEO_IMAGE => [
                'prompt' => $request->prompt,
                'image_url' => self::requireImageUrl($request),
                'resolution' => $request->options['resolution'] ?? '720p',
                'duration' => self::duration($request->options['duration'] ?? null),
                'aspect_ratio' => self::SEEDANCE_RATIOS[$format] ?? self::SEEDANCE_RATIOS[Project::DEFAULT_FORMAT],
                'generate_audio' => (bool) ($request->options['generate_audio'] ?? true),
            ],
            self::TRANSCRIBE => [
                'audio_url' => self::requireUrl($request, 'audio_url'),
                'task' => 'transcribe',
                'chunk_level' => 'segment',
            ],
            default => throw new InvalidArgumentException("fal endpoint [{$endpointId}] is not supported."),
        };
    }

    /**
     * Pull the media URL out of a finished job's response body.
     *
     * @param  array<string, mixed>  $body
     * @return array{url: string, mime: string}|null
     */
    public static function media(string $endpointId, array $body): ?array
    {
        $capability = self::all()[$endpointId]['capability'] ?? null;

        if ($capability === Capability::Image) {
            $url = $body['images'][0]['url'] ?? null;

            return is_string($url) && $url !== '' ? ['url' => $url, 'mime' => $body['images'][0]['content_type'] ?? 'image/png'] : null;
        }

        if ($capability === Capability::Video) {
            $url = $body['video']['url'] ?? null;

            return is_string($url) && $url !== '' ? ['url' => $url, 'mime' => $body['video']['content_type'] ?? 'video/mp4'] : null;
        }

        return null;
    }

    private static function requireUrl(GenerationRequest $request, string $key): string
    {
        $url = $request->inputs[$key] ?? null;

        if (! is_string($url) || $url === '') {
            throw new InvalidArgumentException("This model needs [{$key}].");
        }

        return $url;
    }

    private static function requireImageUrl(GenerationRequest $request): string
    {
        $url = $request->inputs['image_url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new InvalidArgumentException('This model needs a reference image URL. Run an image first and use its fal link.');
        }

        return $url;
    }

    /** Seedance takes 4 to 15 seconds, or "auto". Five seconds is the default, to keep cost predictable. */
    private static function duration(mixed $value): string
    {
        if ($value === 'auto') {
            return 'auto';
        }

        $seconds = (int) ($value ?? 5);

        return (string) max(4, min(15, $seconds));
    }
}
