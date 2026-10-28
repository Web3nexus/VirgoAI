<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One reference on a film's board: a cast member's sheet, or a location's plate. */
#[Fillable(['film_id', 'kind', 'key', 'name', 'prompt', 'status', 'job', 'image_url', 'image_path', 'price', 'cost', 'error'])]
class FilmAsset extends Model
{
    use HasUlids;

    public const KIND_CAST = 'cast';

    public const KIND_LOCATION = 'location';

    protected function casts(): array
    {
        return ['job' => 'array', 'price' => 'integer', 'cost' => 'integer'];
    }

    public function film(): BelongsTo
    {
        return $this->belongsTo(Film::class);
    }
}
