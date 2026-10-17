<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One accepted provider event. Its unique (provider, event_id) pair is what makes a redelivery harmless.
 */
#[Fillable(['provider', 'event_id', 'type', 'outcome', 'purchase_id'])]
class PaymentEvent extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(fn (self $event) => $event->created_at ??= now());
    }
}
