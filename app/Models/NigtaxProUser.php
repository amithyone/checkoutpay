<?php

namespace App\Models;

use App\Notifications\NigtaxProResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class NigtaxProUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'nigtax_pro_users';

    protected $fillable = [
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new NigtaxProResetPasswordNotification($token));
    }
}
