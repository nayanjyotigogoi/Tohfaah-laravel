<?php

namespace App\Models;

use App\Models\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use UsesUuid;

    protected $fillable = [
        'user_id',
        'package_id',
        'credits_purchased',
        'amount_cents',
        'currency',
        'stripe_payment_id',
        'status',
        'gift_id',
        'memory_map_id',
        'razorpay_payment_id',
        'razorpay_order_id',
        'razorpay_signature',
    ];

    public function gift()
    {
        return $this->belongsTo(Gift::class);
    }

    public function memoryMap()
    {
        return $this->belongsTo(MemoryMap::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
