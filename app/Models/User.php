<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'email',
        'password',
        'full_name',
        'avatar_url',
        'terms_accepted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'terms_accepted_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];
    public function gifts()
    {
        return $this->hasMany(Gift::class, 'sender_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
