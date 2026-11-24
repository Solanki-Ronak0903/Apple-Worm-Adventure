<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLevelReward extends Model
{
    protected $fillable = [
        'user_id',
        'level_id',
        'reward_code',
        'quantity',
        'coins_awarded',
        'metadata',
        'granted_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'granted_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }
}
