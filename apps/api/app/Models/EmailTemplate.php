<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * An email the platform sends, with placeholders like {{name}}. The owner edits these in the admin area.
 */
#[Fillable(['key', 'name', 'subject', 'body', 'enabled'])]
class EmailTemplate extends Model
{
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
