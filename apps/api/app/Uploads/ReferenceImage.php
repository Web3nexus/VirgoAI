<?php

namespace App\Uploads;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reference images: the only images a user may attach. They are checked by content, not by name, then re-encoded
 * so no metadata or hidden payload survives. Each file lives under its owner's folder.
 */
final class ReferenceImage
{
    public const MAX_KILOBYTES = 10240;

    public const MIN_PIXELS = 64;

    /** Types a reference may be. SVG is refused: it can carry scripts. */
    public const MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    public static function folder(User $user): string
    {
        return 'references/'.$user->id.'/';
    }

    /**
     * Re-encode the upload and store it. Returns the public URL, or throws when the pixels do not decode.
     */
    public static function store(User $user, UploadedFile $file): string
    {
        $max = (int) config('virgoai.references.max_pixels', 4096);
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new RuntimeException('That file is not a readable image.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width > $max || $height > $max || $width < self::MIN_PIXELS || $height < self::MIN_PIXELS) {
            imagedestroy($source);
            throw new RuntimeException('The image must be between '.self::MIN_PIXELS." and {$max} pixels on each side.");
        }

        // Re-encoding drops EXIF and any trailing data. PNG keeps transparency; everything else becomes JPEG.
        $png = $file->extension() === 'png' || $file->extension() === 'webp';
        $name = Str::ulid().($png ? '.png' : '.jpg');
        $path = self::folder($user).$name;

        ob_start();
        $png ? imagepng($source) : imagejpeg($source, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($source);

        Storage::disk('public')->put($path, $bytes);

        return Storage::disk('public')->url($path);
    }

    /**
     * True when the url is a reference this user uploaded. A user cannot point a run at another user's file.
     */
    public static function ownedBy(User $user, string $url): bool
    {
        $base = rtrim(Storage::disk('public')->url(''), '/').'/';
        if (! str_starts_with($url, $base)) {
            return false;
        }

        $path = substr($url, strlen($base));

        return str_starts_with($path, self::folder($user)) && ! str_contains($path, '..');
    }
}
