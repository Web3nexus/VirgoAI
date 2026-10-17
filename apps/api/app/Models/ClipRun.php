<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One request for clips from a source: "the 5 best moments about pricing, 30 to 90 seconds each".
 */
#[Fillable(['user_id', 'clip_source_id', 'generation_id', 'request', 'model_key', 'count', 'min_seconds', 'max_seconds', 'status', 'error'])]
class ClipRun extends Model
{
    use HasUlids;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ClipSource::class, 'clip_source_id');
    }

    public function clips(): HasMany
    {
        return $this->hasMany(Clip::class)->orderBy('position');
    }
}
