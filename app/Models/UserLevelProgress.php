<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserLevelProgress extends Model { protected $table='user_level_progress'; protected $fillable=['user_id','level_id','unlocked','completed','best_score','stars','golden_apples_collected','best_moves','completed_at']; protected function casts(): array { return ['unlocked'=>'boolean','completed'=>'boolean','completed_at'=>'datetime']; } public function user(){return $this->belongsTo(User::class);} public function level(){return $this->belongsTo(Level::class);} }
