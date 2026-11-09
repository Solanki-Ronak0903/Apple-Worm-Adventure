<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RewardClaim extends Model { protected $fillable=['user_id','game_session_id','provider','action','claim_token','status','reward_payload','granted_at']; protected function casts(): array {return ['reward_payload'=>'array','granted_at'=>'datetime'];} }
