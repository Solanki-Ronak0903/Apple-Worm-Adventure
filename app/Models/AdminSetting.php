<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    protected $fillable = ['key', 'group', 'value', 'updated_by'];

    /**
     * Safe application defaults used before an administrator saves settings.
     * Keep these aligned with the behaviour of the application controllers.
     */
    public static function defaults(): array
    {
        return [
            'allow_registration' => true,
            'require_email_verification' => false,
            'leaderboard_enabled' => true,
            'rewarded_actions_enabled' => true,
            'new_player_starting_coins' => 100,
        ];
    }

    public static function getValue(string $key, mixed $fallback = null): mixed
    {
        $record = static::query()->where('key', $key)->first();
        if (! $record) {
            return $fallback ?? (static::defaults()[$key] ?? null);
        }

        $decoded = json_decode($record->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : ($fallback ?? (static::defaults()[$key] ?? null));
    }

    public static function allValues(): array
    {
        $values = static::defaults();
        foreach (static::query()->get(['key', 'value']) as $record) {
            $decoded = json_decode($record->value, true);
            if (json_last_error() === JSON_ERROR_NONE && array_key_exists($record->key, $values)) {
                $values[$record->key] = $decoded;
            }
        }

        return $values;
    }

    public static function putValue(string $key, mixed $value, string $group, int $adminId): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => json_encode($value, JSON_THROW_ON_ERROR),
                'updated_by' => $adminId,
            ]
        );
    }
}
