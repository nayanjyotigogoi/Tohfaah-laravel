<?php

namespace App\Models;

use App\Models\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapMemory extends Model
{
    use UsesUuid;

    protected $fillable = [
        'memory_map_id',
        'user_id',

        'title',
        'badge',
        'message',
        'photo_url',

        'latitude',
        'longitude',

        'memory_date',
        'display_order',
    ];

    protected $casts = [
        'memory_date' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function memoryMap(): BelongsTo
    {
        return $this->belongsTo(MemoryMap::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
