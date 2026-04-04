<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'autoload',
    ];

    protected $casts = [
        'autoload' => 'boolean',
    ];

    /**
     * Get a setting value.
     */
    public static function get(string $key, mixed $default = null, ?string $group = null): mixed
    {
        $cacheKey = self::getCacheKey($key, $group);

        return Cache::rememberForever($cacheKey, function () use ($key, $default, $group) {
            $query = self::where('key', $key);

            if ($group) {
                $query->where('group', $group);
            }

            $setting = $query->first();

            if (!$setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, mixed $value, ?string $group = 'general', ?string $type = null): self
    {
        $type = $type ?? self::detectType($value);
        $serializedValue = self::serializeValue($value, $type);

        $setting = self::updateOrCreate(
            ['key' => $key, 'group' => $group],
            ['value' => $serializedValue, 'type' => $type]
        );

        // Clear cache
        Cache::forget(self::getCacheKey($key, $group));

        return $setting;
    }

    /**
     * Check if a setting exists.
     */
    public static function has(string $key, ?string $group = null): bool
    {
        $query = self::where('key', $key);

        if ($group) {
            $query->where('group', $group);
        }

        return $query->exists();
    }

    /**
     * Remove a setting.
     */
    public static function remove(string $key, ?string $group = null): bool
    {
        $query = self::where('key', $key);

        if ($group) {
            $query->where('group', $group);
        }

        $deleted = $query->delete();

        Cache::forget(self::getCacheKey($key, $group));

        return $deleted > 0;
    }

    /**
     * Get all settings for a group.
     */
    public static function getGroup(string $group): array
    {
        return Cache::rememberForever("settings.group.{$group}", function () use ($group) {
            return self::where('group', $group)
                ->get()
                ->mapWithKeys(fn ($setting) => [
                    $setting->key => self::castValue($setting->value, $setting->type)
                ])
                ->toArray();
        });
    }

    /**
     * Get all autoloaded settings.
     */
    public static function getAutoloaded(): array
    {
        return Cache::rememberForever('settings.autoloaded', function () {
            return self::where('autoload', true)
                ->get()
                ->mapWithKeys(fn ($setting) => [
                    "{$setting->group}.{$setting->key}" => self::castValue($setting->value, $setting->type)
                ])
                ->toArray();
        });
    }

    /**
     * Clear all settings cache.
     */
    public static function clearCache(): void
    {
        Cache::forget('settings.autoloaded');

        self::query()->each(function ($setting) {
            Cache::forget(self::getCacheKey($setting->key, $setting->group));
        });
    }

    /**
     * Get cache key for a setting.
     */
    protected static function getCacheKey(string $key, ?string $group): string
    {
        return "settings.{$group}.{$key}";
    }

    /**
     * Detect the type of a value.
     */
    protected static function detectType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'array',
            is_object($value) => 'json',
            default => 'string',
        };
    }

    /**
     * Serialize a value for storage.
     */
    protected static function serializeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'array', 'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    /**
     * Cast a value to its proper type.
     */
    protected static function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value === '1',
            'integer' => (int) $value,
            'float' => (float) $value,
            'array', 'json' => json_decode($value, true),
            default => $value,
        };
    }
}
