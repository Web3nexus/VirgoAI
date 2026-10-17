<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * One step of a production, with the tokens or units it used. The price is what the user is charged for
 * the step; the cost is what the provider charged us. Only the owner sees cost.
 */
#[Fillable(['user_id', 'production_type', 'production_id', 'step', 'provider_key', 'model_key', 'input_tokens', 'output_tokens', 'units', 'unit_label', 'cost_micros', 'price_micros'])]
class UsageEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(fn (self $event) => $event->created_at ??= now());
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'units' => 'float'];
    }
}
