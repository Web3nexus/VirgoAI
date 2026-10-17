<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every change the owner makes in the admin area: roles, suspensions, credits, prices, margin.
 */
#[Fillable(['actor_user_id', 'action', 'subject_type', 'subject_id', 'details'])]
class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $log) => $log->created_at ??= now());
    }

    public static function record(?User $actor, string $action, ?Model $subject = null, array $details = []): void
    {
        static::create([
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject ? (string) $subject->getKey() : null,
            'details' => $details ?: null,
        ]);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
