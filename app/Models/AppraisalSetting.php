<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppraisalSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getJson(string $key, mixed $default = null): mixed
    {
        $value = self::where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    public static function putJson(string $key, mixed $value): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($value)],
        );
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::where('key', $key)->value('value');

        return $value === null ? $default : $value;
    }

    public static function put(string $key, mixed $value): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value],
        );
    }
}
