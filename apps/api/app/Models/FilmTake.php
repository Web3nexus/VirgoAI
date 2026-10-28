<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One call to the video model: 4 to 15 seconds, with the board references it is shot from. */
#[Fillable(['film_id', 'chapter', 'position', 'seconds', 'prompt', 'refs', 'status', 'attempts', 'job', 'clip_url', 'clip_path', 'normalized_path', 'price', 'cost', 'error'])]
class FilmTake extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'refs' => 'array',
            'job' => 'array',
            'chapter' => 'integer',
            'position' => 'integer',
            'seconds' => 'integer',
            'attempts' => 'integer',
            'price' => 'integer',
            'cost' => 'integer',
        ];
    }

    public function film(): BelongsTo
    {
        return $this->belongsTo(Film::class);
    }
}
