<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A provider API key saved from the admin area. The value is encrypted at rest with APP_KEY.
 */
#[Fillable(['name', 'value', 'last4', 'updated_by'])]
class ProviderSecret extends Model
{
    protected $hidden = ['value'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return ['value' => 'encrypted'];
    }
}
