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

    /** Cheap and good: chosen from the fal catalogue by price per unit. Field names checked against fal's schema. */
    public const IMAGE_FLUX_SCHNELL = 'fal-ai/flux/schnell';

    public const IMAGE_NANO_BANANA = 'fal-ai/nano-banana-2';

    public const VIDEO_SEEDANCE_FAST = 'bytedance/seedance-2.0/fast/image-to-video';

    public const VIDEO_HAILUO = 'fal-ai/minimax/hailuo-02/standard/image-to-video';

    public const VIDEO_KLING = 'fal-ai/kling-video/v3/standard/image-to-video';

    public const IMAGE_GEMINI = 'fal-ai/gemini-25-flash-image';

    /** Google Veo, the fast tiers: $0.10 per second without audio on fal. */
    public const VIDEO_VEO_31_FAST = 'fal-ai/veo3.1/fast/image-to-video';

    public const VIDEO_VEO_3_FAST = 'fal-ai/veo3/fast/image-to-video';

    /** Speech-to-text for clipping. Internal: it is not offered in the model picker. */
    public const TRANSCRIBE = 'fal-ai/whisper';

    /** Shown in the model picker. The edit endpoint is chosen automatically when a reference image is given. */
    private const SELECTABLE = [
        self::IMAGE_TEXT,
        self::IMAGE_FLUX_SCHNELL,
        self::IMAGE_NANO_BANANA,
        self::VIDEO_IMAGE,
        self::VIDEO_SEEDANCE_FAST,
        self::VIDEO_HAILUO,
        self::VIDEO_KLING,
        self::IMAGE_GEMINI,
        self::VIDEO_VEO_31_FAST,
        self::VIDEO_VEO_3_FAST,
    ];

    /** Image size per project format, for GPT Image. Its sizes are fixed, so the closest one is used. */
    private const GPT_IMAGE_SIZES = [
        'landscape_16_9' => '1536x1024',
        'vertical_9_16' => '1024x1536',
        'square_1_1' => '1024x1024',
        'portrait_4_5' => '1024x1536',
    ];

    /** Image size per project format, for FLUX schnell (its own enum). */
    private const FLUX_SIZES = [
        'landscape_16_9' => 'landscape_16_9',
        'vertical_9_16' => 'portrait_16_9',
        'square_1_1' => 'square_hd',
        'portrait_4_5' => 'portrait_4_3',
    ];

    /** Aspect ratio per project format, for Nano Banana 2. 4:5 is not confirmed, so 3:4 is used. */
    private const NANO_BANANA_RATIOS = [
        'landscape_16_9' => '16:9',
        'vertical_9_16' => '9:16',
        'square_1_1' => '1:1',
        'portrait_4_5' => '3:4',
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
            self::IMAGE_FLUX_SCHNELL => [
                'capability' => Capability::Image,
                'category' => 'text-to-image',
                'selectable' => true,
                'fields' => ['prompt', 'image_size', 'num_images', 'output_format'],
            ],
            self::IMAGE_NANO_BANANA => [
                'capability' => Capability::Image,
                'category' => 'text-to-image',
                'selectable' => true,
                'fields' => ['prompt', 'aspect_ratio', 'resolution', 'num_images', 'output_format'],
            ],
            self::VIDEO_SEEDANCE_FAST => [
                'capability' => Capability::Video,
                'category' => 'image-to-video',
                'selectable' => true,
                'fields' => ['prompt', 'image_url', 'resolution', 'duration', 'aspect_ratio', 'generate_audio'],
            ],
            self::VIDEO_HAILUO => [
                'capability' => Capability::Video,
                'category' => 'image-to-video',
                'selectable' => true,
                'fields' => ['prompt', 'image_url', 'resolution', 'duration'],
            ],
            self::VIDEO_KLING => [
                'capability' => Capability::Video,
                'category' => 'image-to-video',
                'selectable' => true,
                'fields' => ['prompt', 'start_image_url', 'duration', 'generate_audio'],
            ],
            self::IMAGE_GEMINI => [
                'capability' => Capability::Image,
                'category' => 'text-to-image',
                'selectable' => true,
                'fields' => ['prompt', 'aspect_ratio', 'num_images', 'output_format'],
            ],
            self::VIDEO_VEO_31_FAST => [
                'capability' => Capability::Video,
                'category' => 'image-to-video',
                'selectable' => true,
                'fields' => ['prompt', 'image_url', 'duration', 'aspect_ratio', 'resolution', 'generate_audio'],
            ],
            self::VIDEO_VEO_3_FAST => [
                'capability' => Capability::Video,
                'category' => 'image-to-video',
                'selectable' => true,
                'fields' => ['prompt', 'image_url', 'duration', 'aspect_ratio', 'resolution', 'generate_audio'],
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
     * The image-to-video endpoints a full video can be made with.
     *
     * @return list<string>
     */
    public static function selectableVideoIds(): array
    {
        return array_values(array_filter(self::SELECTABLE, fn (string $id) => self::all()[$id]['capability'] === Capability::Video));
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
            self::VIDEO_IMAGE, self::VIDEO_SEEDANCE_FAST => [
                'prompt' => $request->prompt,
                'image_url' => self::requireImageUrl($request),
                'resolution' => $request->options['resolution'] ?? '720p',
                'duration' => self::duration($request->options['duration'] ?? null),
                'aspect_ratio' => self::SEEDANCE_RATIOS[$format] ?? self::SEEDANCE_RATIOS[Project::DEFAULT_FORMAT],
                'generate_audio' => (bool) ($request->options['generate_audio'] ?? true),
            ],
            self::IMAGE_FLUX_SCHNELL => [
                'prompt' => $request->prompt,
                'image_size' => self::FLUX_SIZES[$format] ?? self::FLUX_SIZES[Project::DEFAULT_FORMAT],
                'num_images' => 1,
                'output_format' => 'png',
            ],
            self::IMAGE_NANO_BANANA => [
                'prompt' => $request->prompt,
                'aspect_ratio' => self::NANO_BANANA_RATIOS[$format] ?? self::NANO_BANANA_RATIOS[Project::DEFAULT_FORMAT],
                'resolution' => '1K',
                'num_images' => 1,
                'output_format' => 'png',
            ],
            self::IMAGE_GEMINI => [
                'prompt' => $request->prompt,
                'aspect_ratio' => self::NANO_BANANA_RATIOS[$format] ?? self::NANO_BANANA_RATIOS[Project::DEFAULT_FORMAT],
                'num_images' => 1,
                'output_format' => 'png',
            ],
            self::VIDEO_VEO_31_FAST, self::VIDEO_VEO_3_FAST => [
                'prompt' => $request->prompt,
                'image_url' => self::requireImageUrl($request),
                'duration' => self::veoDuration($request->options['duration'] ?? null),
                // Veo takes only 16:9 or 9:16. Other shapes let Veo choose.
                'aspect_ratio' => match ($format) {
                    'landscape_16_9' => '16:9',
                    'vertical_9_16' => '9:16',
                    default => 'auto',
                },
                'resolution' => ($request->options['resolution'] ?? '720p') === '1080p' ? '1080p' : '720p',
                'generate_audio' => (bool) ($request->options['generate_audio'] ?? true),
            ],
            self::VIDEO_HAILUO => [
                'prompt' => $request->prompt,
                'image_url' => self::requireImageUrl($request),
                // Hailuo takes 6 or 10 seconds as text, and 512P is its cheapest tier.
                'duration' => ((int) ($request->options['duration'] ?? 6)) > 6 ? '10' : '6',
                'resolution' => ($request->options['resolution'] ?? '720p') === '720p' ? '768P' : '512P',
            ],
            self::VIDEO_KLING => [
                'prompt' => $request->prompt,
                'start_image_url' => self::requireImageUrl($request),
                'duration' => (string) max(3, min(15, (int) ($request->options['duration'] ?? 5))),
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

    /** Veo takes 4, 6 or 8 seconds, as text. The shortest that covers the request is used. */
    private static function veoDuration(mixed $value): string
    {
        $seconds = (int) ($value ?? 8);

        return match (true) {
            $seconds <= 4 => '4s',
            $seconds <= 6 => '6s',
            default => '8s',
        };
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
