<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Small platform settings the owner edits in the admin area, such as the margin percentage.
 */
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function read(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->find($key);

        return $setting?->value['v'] ?? $default;
    }

    public static function write(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => ['v' => $value]]);
    }
}
