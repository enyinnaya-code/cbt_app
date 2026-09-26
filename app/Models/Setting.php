<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable site settings (store links, bank details, prices). Read once per request, then served from memory.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    /** @var array<string,?string>|null */
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::values()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /** Saves several at once. Blank values are stored as empty, which reads back as "not set". */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value === null ? null : trim((string) $value)]);
        }

        self::$cache = null;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    /** @return array<string,?string> */
    private static function values(): array
    {
        return self::$cache ??= static::query()->pluck('value', 'key')->all();
    }
}
