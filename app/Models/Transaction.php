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
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
