<?php

namespace App\Media;

use App\Models\Film;
use App\Models\Generation;
use App\Models\Production;
use DomainException;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the files behind a video or image and keeps the record. Usage and billing rows are never touched,
 * so a removed video still shows what it cost.
 */
final class MediaRemover
{
    public function removeProduction(Production $production): void
    {
        if (! $production->isTerminal()) {
            throw new DomainException('This video is still rendering. You can delete it once it finishes.');
        }

        Storage::disk('public')->deleteDirectory("productions/{$production->id}");

        if ($production->generation_id !== null) {
            $generation = Generation::query()->find($production->generation_id);
            if ($generation !== null && $generation->media_deleted_at === null) {
                $this->removeGeneration($generation);
            }
        }

        $production->scenes()->update(['keyframe_path' => null, 'keyframe_url' => null, 'clip_path' => null]);
        $production->forceFill(['output_path' => null, 'media_deleted_at' => now()])->save();
    }

    /** Delete a film's board, takes, chapters and joined file. The film's record and its cost stay. */
    public function removeFilm(Film $film): void
    {
        if (! $film->isTerminal()) {
            throw new DomainException('This film is still being made. You can delete it once it finishes.');
        }

        if ($film->output_path !== null) {
            Storage::disk('public')->delete($film->output_path);
        }
        Storage::disk('public')->deleteDirectory("films/{$film->id}");

        if ($film->generation_id !== null) {
            $generation = Generation::query()->find($film->generation_id);
            if ($generation !== null && $generation->media_deleted_at === null) {
                $this->removeGeneration($generation);
            }
        }

        $film->takes()->update(['clip_url' => null, 'clip_path' => null, 'normalized_path' => null]);
        $film->assets()->update(['image_url' => null, 'image_path' => null]);
        $film->forceFill(['output_path' => null, 'media_deleted_at' => now()])->save();
    }

    public function removeGeneration(Generation $generation): void
    {
        if ($generation->status === Generation::STATUS_RUNNING) {
            throw new DomainException('This run is still rendering. You can delete it once it finishes.');
        }

        if ($generation->output_path !== null) {
            Storage::disk('public')->delete($generation->output_path);
        }

        $generation->forceFill(['output_path' => null, 'media_deleted_at' => now()])->save();
    }
}
