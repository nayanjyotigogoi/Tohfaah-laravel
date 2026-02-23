<?php

namespace App\Models;

use App\Models\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemoryMap extends Model
{
    use UsesUuid;

    protected $fillable = [
        'owner_id',
        'title',
        'description',

        // Lifecycle
        'status',              // draft | active | archived
        'payment_status',      // unpaid | paid | coupon_redeemed
        'amount',

        // Access
        'share_token',
        'max_participants',

        // Password Lock
        'has_password',
        'password_hash',
        'password_hint',

        // Timestamps
        'published_at',
    ];

    protected $casts = [
        'has_password' => 'boolean',
        'published_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MemoryMapParticipant::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(MapMemory::class)
            ->orderBy('display_order');
    }

    /*
    |--------------------------------------------------------------------------
    | Business Helpers
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaid(): bool
    {
        return in_array($this->payment_status, ['paid', 'coupon_redeemed']);
    }

    public function canBePublished(): bool
    {
        return $this->isDraft() && $this->isPaid();
    }

    public function seatCount(): int
    {
        return $this->participants()
            ->whereIn('status', ['invited', 'active'])
            ->count();
    }

    public function hasAvailableSeats(): bool
    {
        return $this->seatCount() < $this->max_participants;
    }
}
