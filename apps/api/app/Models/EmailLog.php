<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['template_key', 'to_email', 'subject', 'status', 'error', 'user_id'])]
class EmailLog extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(fn (self $log) => $log->created_at ??= now());
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
