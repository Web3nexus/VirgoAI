<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment for a plan, a renewal, or a top-up. Only a paid purchase grants credit, and a purchase is
 * fulfilled once.
 */
#[Fillable(['user_id', 'kind', 'item_key', 'interval', 'amount_micros', 'credits_micros', 'status', 'provider', 'provider_ref', 'paid_at', 'created_by', 'checkout_url', 'provider_subscription_id'])]
class Purchase extends Model
{
    use HasUlids;

    public const KIND_SUBSCRIPTION = 'subscription';

    public const KIND_RENEWAL = 'subscription_renewal';

    public const KIND_PACK = 'credit_pack';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'amount_micros' => 'integer',
            'credits_micros' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
