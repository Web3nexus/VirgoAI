<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A long video the user wants clipped. An upload is stored and transcribed. A YouTube link is played in full.
 */
#[Fillable(['user_id', 'kind', 'status', 'original_name', 'youtube_id', 'storage_path', 'duration_seconds', 'width', 'height', 'transcript', 'transcript_generation_id', 'error'])]
class ClipSource extends Model
{
    use HasUlids;

    public const KIND_UPLOAD = 'upload';

    public const KIND_YOUTUBE = 'youtube';

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROBING = 'probing';

    public const STATUS_TRANSCRIBING = 'transcribing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'float',
            'transcript' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ClipRun::class)->latest('created_at');
    }
}
