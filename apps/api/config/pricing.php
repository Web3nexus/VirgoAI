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
