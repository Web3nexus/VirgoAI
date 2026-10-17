<?php

namespace App\Enums;

enum Capability: string
{
    case Text = 'text';
    case Vision = 'vision';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Music = 'music';
    case Subtitle = 'subtitle';
    case Upscale = 'upscale';
    case Embedding = 'embedding';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Vision => 'Vision',
            self::Image => 'Image',
            self::Video => 'Video',
            self::Audio => 'Audio / speech',
            self::Music => 'Music',
            self::Subtitle => 'Subtitles',
            self::Upscale => 'Upscale',
            self::Embedding => 'Embedding',
        };
    }
}
