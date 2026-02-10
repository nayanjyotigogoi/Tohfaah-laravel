<?php

namespace App\Models;

use App\Models\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gift extends Model
{
    use UsesUuid;

    protected $fillable = [
        // System
        'sender_id',
        'template_type',
        'status',
        'payment_status',
        'amount',
        'coupon_code',
        'coupon_applied',
        'share_token',

        // Identity
        'recipient_name',
        'sender_name',

        // Lock
        'has_secret_question',
        'secret_question',
        'secret_answer_hash',
        'secret_hint',

        // Template engine
        'config',

        // Analytics
        'view_count',
        'first_viewed_at',

        // Lifecycle
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'config' => 'array',
        'has_secret_question' => 'boolean',
        'coupon_applied' => 'boolean',
        'expires_at' => 'datetime',
        'published_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function memories(): HasMany
    {
        return $this->hasMany(GiftMemory::class)->orderBy('display_order');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GiftPhoto::class)->orderBy('display_order');
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

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isPaid(): bool
    {
        return in_array($this->payment_status, ['paid', 'coupon_redeemed']);
    }

    public function canBePublished(): bool
    {
        return $this->isDraft() && $this->isPaid();
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function recordView(): void
    {
        $this->increment('view_count');

        if (!$this->first_viewed_at) {
            $this->update([
                'first_viewed_at' => now()
            ]);
        }
    }
}
