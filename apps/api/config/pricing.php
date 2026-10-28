<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default margin
    |--------------------------------------------------------------------------
    |
    | The sell price is the provider cost plus this percentage. The owner can change it in the admin
    | area; the stored value wins over this default. A 40% markup keeps about 28.6% of each price.
    |
    */
    'default_margin_percent' => (int) env('PRICING_DEFAULT_MARGIN_PERCENT', 40),

    /*
    |--------------------------------------------------------------------------
    | Margin floor
    |--------------------------------------------------------------------------
    |
    | The owner cannot set the margin below this. Payment fees come out of the margin, so a margin near zero
    | loses money on every purchase.
    |
    */
    'min_margin_percent' => (int) env('PRICING_MIN_MARGIN_PERCENT', 40),

    /*
    |--------------------------------------------------------------------------
    | Payment fees (planning assumption)
    |--------------------------------------------------------------------------
    |
    | The fee a payment processor keeps from each charge, used only to show the owner what is left after fees
    | on the rate card. These are placeholders until the processor's contract rates are entered here.
    |
    */
    'payment_fees' => [
        'percent' => (float) env('PRICING_FEE_PERCENT', 5),
        'fixed_usd' => (float) env('PRICING_FEE_FIXED_USD', 0.50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic reference prices
    |--------------------------------------------------------------------------
    |
    | Anthropic publishes no pricing API, so these come from the public pricing page. Values are USD
    | per million tokens. A model only appears here once its row on that page was read clearly. Any
    | other Claude model stays unpriced until the owner sets its price in the admin area.
    |
    */
    'anthropic' => [
        'long_prompt_tokens' => 100000,
        'verified_at' => '2026-10-16',
        'source_url' => 'https://docs.anthropic.com/en/docs/about-claude/pricing',
        'models' => [
            'claude-sonnet-5-5' => ['input_mtok' => 2.00, 'output_mtok' => 10.00],
            'claude-opus-5-5' => ['input_mtok' => 4.00, 'output_mtok' => 20.00],
            'claude-fable-5-1' => ['input_mtok' => 10.00, 'output_mtok' => 50.00],
            // Haiku is tiered: prompts over 100,000 input tokens use the *_long rates.
            'claude-haiku-5-5' => [
                'input_mtok' => 0.10,
                'output_mtok' => 0.50,
                'input_mtok_long' => 0.50,
                'output_mtok_long' => 2.50,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Units fal can bill, and how many units a run uses
    |--------------------------------------------------------------------------
    |
    | A fal unit that is not listed here is refused, not guessed. `second` bills the video length.
    | `image` and `video` bill one unit per output. Any other unit names are stored from the API
    | but refused at quote time until the mapping is added.
    |
    */
    'fal_units' => ['image', 'video', 'second', 'seconds'],

    /*
    |--------------------------------------------------------------------------
    | fal rate table (USD, read from fal's model pages)
    |--------------------------------------------------------------------------
    |
    | The price a call is billed at depends on what is sent: GPT Image costs more at a larger size and a higher
    | quality, and video costs more at 1080p than at 720p. The stored fal price is one number per model, so it
    | cannot price these. This table does, and it is checked before every charge.
    |
    | A call whose quality, size or resolution is not in the table is refused, never guessed at. Duration is
    | billed as the provider bills it (see FalEndpoints::billableSeconds), not as requested.
    |
    | `unit` is what is counted: `image` (one per output) or `second` (per second of video).
    | `tiers` is keyed by quality, then size for images, and by resolution for video (`any` matches all).
    |
    | Checked on 2026-10-30. Endpoints not listed here fall back to the synced fal price, and the rate card
    | marks them as unverified.
    |
    */
    'fal_rates' => [
        'fal-ai/gpt-image-1.5' => [
            'unit' => 'image',
            'verified' => true,
            'tiers' => [
                'low' => ['1024x1024' => 0.009, '1024x1536' => 0.013, '1536x1024' => 0.013],
                'medium' => ['1024x1024' => 0.034, '1024x1536' => 0.051, '1536x1024' => 0.050],
                'high' => ['1024x1024' => 0.133, '1024x1536' => 0.200, '1536x1024' => 0.199],
            ],
        ],
        // The edit endpoint is priced like text-to-image on fal's listing, but its page was not read. Unverified.
        'fal-ai/gpt-image-1.5/edit' => [
            'unit' => 'image',
            'verified' => false,
            'tiers' => [
                'low' => ['1024x1024' => 0.009, '1024x1536' => 0.013, '1536x1024' => 0.013],
                'medium' => ['1024x1024' => 0.034, '1024x1536' => 0.051, '1536x1024' => 0.050],
                'high' => ['1024x1024' => 0.133, '1024x1536' => 0.200, '1536x1024' => 0.199],
            ],
        ],
        // Seedance 2.0 image to video: $0.3034 a second at 720p, $0.682 at 1080p. Audio does not change the price.
        'bytedance/seedance-2.0/image-to-video' => [
            'unit' => 'second',
            'verified' => true,
            'tiers' => ['720p' => 0.3034, '1080p' => 0.682],
        ],
        // Seedance 2.0 reference to video is billed per 1,000 tokens, and tokens grow with resolution and length.
        // Its price is set to the image-to-video rate, which is the same model at the same resolutions. Unverified
        // until a live call confirms the token count.
        'bytedance/seedance-2.0/reference-to-video' => [
            'unit' => 'second',
            'verified' => false,
            'tiers' => ['720p' => 0.3034, '1080p' => 0.682],
        ],
        // Kling v3 standard: $0.126 a second with audio on, which is how the app calls it.
        'fal-ai/kling-video/v3/standard/image-to-video' => [
            'unit' => 'second',
            'verified' => true,
            'tiers' => ['any' => 0.126],
        ],
        // Veo 3.1 fast: $0.15 a second with audio on, at 720p or 1080p.
        'fal-ai/veo3.1/fast/image-to-video' => [
            'unit' => 'second',
            'verified' => true,
            'tiers' => ['any' => 0.15],
        ],
        // Hailuo 02: the app sends 768P for 720p and 512P for 1080p, at $0.045 and about $0.017 a second.
        'fal-ai/minimax/hailuo-02/standard/image-to-video' => [
            'unit' => 'second',
            // 768P is verified; the 512P figure was read as "roughly", so the entry stays unverified until confirmed.
            'verified' => false,
            'tiers' => ['720p' => 0.045, '1080p' => 0.017],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Free providers
    |--------------------------------------------------------------------------
    |
    | Providers that cost nothing to run. `echo` is the offline test model. Any other provider must
    | have price rules, or its runs are refused.
    |
    */
    'free_providers' => ['echo'],

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    */
    'micros_per_usd' => 1_000_000,

    /*
    |--------------------------------------------------------------------------
    | Payment provider by country
    |--------------------------------------------------------------------------
    |
    | Customers in these countries pay through Flutterwave. Everyone else pays through Paddle.
    |
    */
    'flutterwave_countries' => [
        'DZ', 'AO', 'BJ', 'BW', 'BF', 'BI', 'CM', 'CV', 'CF', 'TD', 'KM', 'CG', 'CD', 'CI', 'DJ', 'EG', 'GQ', 'ER',
        'SZ', 'ET', 'GA', 'GM', 'GH', 'GN', 'GW', 'KE', 'LS', 'LR', 'LY', 'MG', 'MW', 'ML', 'MR', 'MU', 'MA', 'MZ',
        'NA', 'NE', 'NG', 'RW', 'ST', 'SN', 'SC', 'SL', 'SO', 'ZA', 'SS', 'SD', 'TZ', 'TG', 'TN', 'UG', 'ZM', 'ZW', 'EH',
    ],

];
