<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameSession extends Model
{
    protected $fillable = ['user_id', 'level_id', 'status', 'score', 'moves_used', 'apples_collected', 'revives_used', 'hints_used', 'boosters_used', 'state', 'started_at', 'finished_at'];
    protected function casts(): array
    {
        return ['state' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
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
