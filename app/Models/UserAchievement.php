<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserAchievement extends Model { protected $fillable=['user_id','achievement_id','progress','unlocked_at']; protected function casts(): array {return ['progress'=>'array','unlocked_at'=>'datetime'];} }
