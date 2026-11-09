<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    protected $fillable = ['code', 'name', 'description', 'icon', 'criteria'];
    protected function casts(): array
    {
        return ['criteria' => 'array'];
    }

    public function userAchievements()
    {
        return $this->hasMany(UserAchievement::class);
    }
}
