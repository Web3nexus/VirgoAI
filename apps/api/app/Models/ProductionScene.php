<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['production_id', 'position', 'title', 'image_prompt', 'video_prompt', 'status', 'keyframe_job', 'keyframe_url', 'keyframe_path', 'keyframe_price', 'keyframe_cost', 'motion_job', 'clip_path', 'motion_price', 'motion_cost', 'error'])]
class ProductionScene extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'keyframe_job' => 'array',
            'motion_job' => 'array',
            'keyframe_price' => 'integer',
            'keyframe_cost' => 'integer',
            'motion_price' => 'integer',
            'motion_cost' => 'integer',
        ];
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }
}
