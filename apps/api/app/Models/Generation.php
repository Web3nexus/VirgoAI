<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'project_id', 'tool', 'capability', 'provider_key', 'model_key', 'prompt', 'options', 'status', 'output_type', 'output_text', 'output_path', 'error', 'cost_usd', 'provider_job', 'price_micros', 'cost_micros'])]
class Generation extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'provider_job' => 'array',
            'price_micros' => 'integer',
            'cost_micros' => 'integer',
            'cost_usd' => 'decimal:4',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
