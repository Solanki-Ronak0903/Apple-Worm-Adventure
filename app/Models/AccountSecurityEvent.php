<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountSecurityEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'event', 'ip_address', 'user_agent', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
