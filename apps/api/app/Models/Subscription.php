<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's plan. Entitled means active and inside the paid period. Past due means the period ended
 * without a payment; it keeps no credit for the grace window and then ends.
 */
#[Fillable(['user_id', 'plan_key', 'interval', 'status', 'current_period_end', 'next_credit_at', 'cancel_at_period_end', 'provider', 'provider_ref', 'ended_at'])]
class Subscription extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_ENDED = 'ended';

    public const INTERVAL_MONTHLY = 'monthly';

    public const INTERVAL_YEARLY = 'yearly';

    /** Days after a missed renewal before the plan ends. */
    public const GRACE_DAYS = 3;

    protected function casts(): array
    {
        return [
            'current_period_end' => 'datetime',
            'next_credit_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEntitled(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->current_period_end->isFuture();
    }
}
