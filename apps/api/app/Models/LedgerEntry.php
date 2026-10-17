<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'generation_id', 'type', 'amount_micros', 'balance_after_micros', 'note', 'actor_user_id'])]
class LedgerEntry extends Model
{
    use HasUlids;

    /** Money added by the owner. */
    public const TYPE_GRANT = 'grant';

    /** Money taken when a run starts (reserved at the quoted price). */
    public const TYPE_CHARGE = 'charge';

    /** Money returned when a run fails or is refunded. */
    public const TYPE_REFUND = 'refund';

    /** Difference between the reserved price and the settled price of a run. */
    public const TYPE_SETTLEMENT = 'settlement';

    /** Monthly credit from a subscription. */
    public const TYPE_SUBSCRIPTION = 'subscription';

    /** Subscription credit that was not used by the end of its cycle. */
    public const TYPE_EXPIRY = 'expiry';

    /** Top-up credit bought by the user. */
    public const TYPE_PURCHASE = 'purchase';

    /** Manual correction by the owner, deducted or added. */
    public const TYPE_ADJUSTMENT = 'adjustment';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
