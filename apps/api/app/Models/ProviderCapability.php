<?php

namespace App\Models;

use App\Enums\Capability;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ai_model_id', 'capability', 'constraints'])]
class ProviderCapability extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capability' => Capability::class,
            'constraints' => 'array',
        ];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }
}
