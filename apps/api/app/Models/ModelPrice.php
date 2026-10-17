<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * What one billing unit of a model costs the platform, in micro-dollars.
 *
 * `unit` says what is counted: `image`, `second`, `video` (fal), or `input_mtok` / `output_mtok`
 * (Anthropic, per million tokens). `source` says where the number came from, so a price the owner
 * typed by hand is never overwritten by a sync.
 */
#[Fillable(['provider_key', 'model_key', 'unit', 'cost_micros', 'source', 'verified_at'])]
class ModelPrice extends Model
{
    public const SOURCE_FAL_API = 'fal_api';

    public const SOURCE_ANTHROPIC_DOCS = 'anthropic_docs';

    public const SOURCE_ADMIN = 'admin';

    protected function casts(): array
    {
        return [
            'cost_micros' => 'integer',
            'verified_at' => 'datetime',
        ];
    }
}
