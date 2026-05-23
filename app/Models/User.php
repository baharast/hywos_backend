<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    protected $fillable = [
        'username',
        'name',
        'email',
        'phone',
        'password',
        'preferred_language',
        'is_active',
        'is_locked',
        'locked_at',
        'locked_by_user_id',
        'locked_reason',
        'disabled_at',
        'disabled_by_user_id',
        'disabled_reason',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'disabled_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function getStatusAttribute(): string
    {
        if (! is_null($this->disabled_at) || $this->is_active === false) {
            return $this->is_locked ? 'locked' : 'disabled';
        }
        if ($this->is_locked) {
            return 'locked';
        }
        return $this->is_active ? 'active' : 'inactive';
    }
}
