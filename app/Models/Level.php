<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Level extends Model { protected $fillable=['number','title','difficulty','grid_rows','grid_cols','config','is_published']; protected function casts(): array { return ['config'=>'array','is_published'=>'boolean']; } public function progress(){return $this->hasMany(UserLevelProgress::class); } }
