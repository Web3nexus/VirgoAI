<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A subscription plan shown on the public pricing page. Prices and credit are micro-dollars.
 */
#[Fillable(['key', 'name', 'tagline', 'monthly_micros', 'yearly_micros', 'monthly_credit_micros', 'features', 'highlighted', 'active', 'sort', 'flutterwave_monthly_plan_id', 'flutterwave_yearly_plan_id', 'paddle_monthly_price_id', 'paddle_yearly_price_id'])]
class Plan extends Model
{
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'highlighted' => 'boolean',
            'active' => 'boolean',
            'monthly_micros' => 'integer',
            'yearly_micros' => 'integer',
            'monthly_credit_micros' => 'integer',
        ];
    }
}
