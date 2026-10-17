<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clip_run_id', 'position', 'start_seconds', 'end_seconds', 'title', 'reason', 'storage_path'])]
class Clip extends Model
{
    use HasUlids;

    public function run(): BelongsTo
    {
        return $this->belongsTo(ClipRun::class, 'clip_run_id');
    }

    protected function casts(): array
    {
        return ['start_seconds' => 'float', 'end_seconds' => 'float'];
    }
}
