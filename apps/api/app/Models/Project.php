<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'description', 'type', 'format', 'preview_url', 'status', 'settings'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /** Input modes a project can be created from. */
    public const TYPES = ['idea', 'youtube_url', 'transcript', 'uploaded_video', 'script'];

    /**
     * Output formats. The key is stored on the project; width and height are the render size
     * the pipeline uses for this format.
     *
     * @var array<string, array{label: string, width: int, height: int, use: string}>
     */
    public const FORMATS = [
        'landscape_16_9' => ['label' => 'Landscape 16:9', 'width' => 1920, 'height' => 1080, 'use' => 'YouTube, desktop, presentations'],
        'vertical_9_16' => ['label' => 'Vertical 9:16', 'width' => 1080, 'height' => 1920, 'use' => 'Shorts, Reels, TikTok'],
        'square_1_1' => ['label' => 'Square 1:1', 'width' => 1080, 'height' => 1080, 'use' => 'Feed posts, LinkedIn'],
        'portrait_4_5' => ['label' => 'Portrait 4:5', 'width' => 1080, 'height' => 1350, 'use' => 'Instagram feed'],
    ];

    public const DEFAULT_FORMAT = 'landscape_16_9';

    /** Lifecycle states for a project. */
    public const STATUSES = ['draft', 'active', 'completed', 'archived'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * @return array{width: int, height: int}
     */
    public function dimensions(): array
    {
        $format = self::FORMATS[$this->format] ?? self::FORMATS[self::DEFAULT_FORMAT];

        return ['width' => $format['width'], 'height' => $format['height']];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }
}
