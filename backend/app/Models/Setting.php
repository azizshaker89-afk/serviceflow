<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected static function booted(): void
    {
        static::saved(fn (Setting $setting) => Cache::forget("settings.{$setting->key}"));
        static::deleted(fn (Setting $setting) => Cache::forget("settings.{$setting->key}"));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(
            "settings.{$key}",
            fn () => static::query()->where('key', $key)->value('value'),
        ) ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function taxRate(): string
    {
        return (string) static::get('tax_rate', config('serviceflow.default_tax_rate'));
    }
}
