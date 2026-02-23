<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryMapParticipant extends Model
{
    protected $fillable = [
        'memory_map_id',
        'email',
        'user_id',
        'role',       // owner | participant
        'status',     // invited | active | removed
        'invited_by',
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
        return $this->belongsTo(User::class);
    }
}
