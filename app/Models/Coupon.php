<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'max_uses',
        'used_count',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function gifts(): HasMany
    {
        return $this->hasMany(Gift::class, 'coupon_code', 'code');
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic
    |--------------------------------------------------------------------------
    */

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasRemainingUses(): bool
    {
        if ($this->max_uses === null) {
            return true; // unlimited
        }

        return $this->used_count < $this->max_uses;
    }

    public function isValid(): bool
    {
        return $this->is_active
            && !$this->isExpired()
            && $this->hasRemainingUses();
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
