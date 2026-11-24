<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = mb_strtolower(trim($value));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function progress()
    {
        return $this->hasMany(UserLevelProgress::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function gameSessions()
    {
        return $this->hasMany(GameSession::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function achievements()
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function securityEvents()
    {
        return $this->hasMany(AccountSecurityEvent::class);
    }
}
