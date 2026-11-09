<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WalletTransaction extends Model { protected $fillable=['user_id','type','amount','balance_after','reference','description','metadata']; protected function casts(): array {return ['metadata'=>'array'];} public function user(){return $this->belongsTo(User::class);} }
