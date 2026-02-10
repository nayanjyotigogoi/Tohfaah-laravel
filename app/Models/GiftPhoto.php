<?php

namespace App\Models;

use App\Models\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class GiftPhoto extends Model
{
    use UsesUuid;

    protected $fillable = [
        'gift_id',
        'image_url',
        'caption',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function gift()
    {
        return $this->belongsTo(Gift::class);
    }
}
