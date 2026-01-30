<?php

namespace App\Models;

use App\Models\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class FreeGift extends Model
{
    use UsesUuid;

    protected $fillable = [
        'sender_id',
        'gift_type',
        'recipient_name',
        'sender_name',
        'gift_data',
        'share_token',
    ];

    protected $casts = [
        'gift_data' => 'array',
    ];
}
