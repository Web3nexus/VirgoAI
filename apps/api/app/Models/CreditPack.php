<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A one-off top-up. It adds credit that does not expire.
 */
#[Fillable(['key', 'name', 'credits_micros', 'price_micros', 'subscriber_discount_percent', 'active', 'sort', 'paddle_price_id', 'paddle_subscriber_price_id'])]
class CreditPack extends Model
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'credits_micros' => 'integer',
            'price_micros' => 'integer',
            'subscriber_discount_percent' => 'integer',
        ];
    }
}
