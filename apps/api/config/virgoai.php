<?php

use App\Ai\Adapters\AnthropicAdapter;
use App\Ai\Adapters\EchoAdapter;
use App\Ai\Adapters\FalAdapter;

return [
    /*
    |--------------------------------------------------------------------------
    | AI provider adapters
    |--------------------------------------------------------------------------
    |
    | Maps a provider key (matching ai_providers.key) to its adapter class. Real
    | providers are added here one at a time. Secrets are never stored here; the
    | DB stores only the *name* of the env variable that holds the credential.
    |
    */
    'providers' => array_filter([
        // Dev/testing only: an offline model that repeats the prompt. Never registered in production.
        'echo' => [
            'adapter' => EchoAdapter::class,
        ],

        // Text: Claude, called directly through Anthropic's API.
        'anthropic' => [
            'adapter' => AnthropicAdapter::class,
        ],

        // Image and video: GPT Image and Seedance, both hosted on fal.ai.
        'fal' => [
            'adapter' => FalAdapter::class,
        ],
    ], fn (string $key) => $key !== 'echo' || in_array(env('APP_ENV', 'production'), ['local', 'testing'], true), ARRAY_FILTER_USE_KEY),

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Optional name → value map resolved by AiProvider::isConfigured(). Prefer
    | reading from the environment variable named by ai_providers.credentials_key.
    |
    */
    'credentials' => [
        'ANTHROPIC_API_KEY' => env('ANTHROPIC_API_KEY'),
        'FAL_KEY' => env('FAL_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic (text)
    |--------------------------------------------------------------------------
    */
    'anthropic' => [
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 8192),
        'timeout_seconds' => (int) env('ANTHROPIC_TIMEOUT_SECONDS', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | fal.ai queue tuning
    |--------------------------------------------------------------------------
    */
    'fal' => [
        'timeout_seconds' => (int) env('FAL_TIMEOUT_SECONDS', 30),
        // Seconds between checks on a queued job.
        'poll_interval_seconds' => (int) env('FAL_POLL_INTERVAL_SECONDS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Clipping
    |--------------------------------------------------------------------------
    |
    | Long videos are probed and transcribed once, then clipped on request. The binaries must be on the
    | server's PATH, or set their full paths. Uploads above post_max_size fail before they reach the app,
    | so raise both upload_max_filesize and post_max_size in php.ini for large files.
    |
    */
    'productions' => [
        // A full video has at most this many scenes. Each scene is one keyframe and one motion clip.
        'max_scenes' => (int) env('PRODUCTION_MAX_SCENES', 6),
        // Seconds of motion per scene.
        'scene_seconds' => 5,
        'max_wait_seconds' => (int) env('PRODUCTION_MAX_WAIT', 3600),
        'tick_seconds' => (int) env('PRODUCTION_TICK_SECONDS', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Director Lab films
    |--------------------------------------------------------------------------
    |
    | A film is planned as chapters of up to ten minutes. Each chapter is a list of takes of 4 to 15 seconds, the
    | longest call Seedance accepts. Takes are sent in a window, so a long film does not flood the queue.
    |
    */
    'films' => [
        'chapter_seconds' => (int) env('FILM_CHAPTER_SECONDS', 600),
        'take_max_seconds' => 15,
        'take_min_seconds' => 4,
        'min_length_seconds' => 15,
        'max_length_seconds' => (int) env('FILM_MAX_LENGTH_SECONDS', 3600),
        'max_cast' => 6,
        'max_locations' => 5,
        // Takes sent to fal at once for one film.
        'max_in_flight' => (int) env('FILM_MAX_IN_FLIGHT', 16),
        // A take that fails is sent again this many times in total before the film fails.
        'max_take_attempts' => 2,
        // Takes joined per tick during assembly, so one tick never runs long.
        'normalize_batch' => 6,
        'max_wait_seconds' => (int) env('FILM_MAX_WAIT', 14400),
        'tick_seconds' => (int) env('FILM_TICK_SECONDS', 15),
        // The most a film may reserve, in micro-dollars at the sell price ($500 by default).
        'max_price_micros' => (int) env('FILM_MAX_PRICE_MICROS', 500_000_000),
    ],

    'clips' => [
        'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),
        'command_timeout_seconds' => (int) env('CLIPS_COMMAND_TIMEOUT', 3600),
        // Largest accepted upload, in kilobytes (2 GB).
        'max_upload_kb' => (int) env('CLIPS_MAX_UPLOAD_KB', 2097152),
        'max_clips' => 10,
        'max_wait_seconds' => (int) env('CLIPS_TRANSCRIBE_MAX_WAIT', 3600),
        // local: our own engine (whisper.cpp, open source, no API). fal: the hosted Whisper endpoint, billed.
        'transcriber' => env('CLIPS_TRANSCRIBER', 'local'),
        'whisper_binary' => env('WHISPER_BINARY', 'whisper-cli'),
        'whisper_model' => env('WHISPER_MODEL', (string) env('HOME', '').'/.virgoai/models/ggml-base.en.bin'),
        'whisper_threads' => (int) env('WHISPER_THREADS', 4),
        'poll_interval_seconds' => (int) env('CLIPS_POLL_INTERVAL', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    |
    | The payment provider signs its webhook calls with this secret. Until it is set, the webhook is off.
    |
    */
    'billing' => [
        // Where the customer returns after paying.
        'frontend_url' => env('APP_FRONTEND_URL', 'http://localhost:5173'),
        'flutterwave' => [
            'base' => 'https://api.flutterwave.com/v3',
            // Recurring plans end after this many cycles unless cancelled. Set long enough to run until cancelled.
            'plan_duration_monthly' => 120,
            'plan_duration_yearly' => 10,
        ],
        'paddle' => [
            // Use https://sandbox-api.paddle.com while testing.
            'base' => env('PADDLE_API_BASE', 'https://api.paddle.com'),
            // Reject a signed webhook older than this many seconds. Guards against replays.
            'tolerance_seconds' => 300,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queued generations
    |--------------------------------------------------------------------------
    */
    'generation' => [
        // A queued render that is still running after this long is marked failed.
        'max_wait_seconds' => (int) env('GENERATION_MAX_WAIT_SECONDS', 1200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media copied from the provider to our public disk
    |--------------------------------------------------------------------------
    */
    'media' => [
        'max_bytes' => (int) env('MEDIA_MAX_BYTES', 524288000),
        'timeout_seconds' => (int) env('MEDIA_TIMEOUT_SECONDS', 600),
        // Videos and images are deleted after this many days. Their records are kept.
        'retention_days' => (int) env('MEDIA_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voices the director may cast
    |--------------------------------------------------------------------------
    |
    | voice_id values the director can assign to characters. Only add an id here
    | after its voice adapter is integrated and verified. Empty means no voice is
    | connected, so dialogue scripts fail validation instead of naming unknown voices.
    |
    */
    'voices' => [],

    // Reference images attached to runs.
    'references' => [
        'daily_limit' => (int) env('REFERENCE_DAILY_LIMIT', 30),
        'max_pixels' => 4096,
    ],

    // Hard ceilings on what one run may cost, whatever the request asks for.
    'guardrails' => [
        'max_run_price_micros' => (int) env('MAX_RUN_PRICE_MICROS', 5_000_000),
        'max_production_price_micros' => (int) env('MAX_PRODUCTION_PRICE_MICROS', 40_000_000),
    ],
];
