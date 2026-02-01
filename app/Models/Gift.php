<?php

namespace App\Models;

use App\Models\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class Gift extends Model
{
    use UsesUuid;

    protected $fillable = [
        'sender_id',
        'template_type',
        'status',
        'share_token',

        'recipient_name',
        'recipient_nickname',
        'sender_name',
        'sender_nickname',

        'has_secret_question',
        'secret_question',
        'secret_answer_hash',
        'secret_hint',

        'message_title',
        'message_body',
        'message_style',

        'has_love_letter',
        'love_letter_content',
        'love_letter_style',

        'has_memories',
        'has_gallery',
        'has_map',
        'has_proposal',

        'sender_location',
        'recipient_location',
        'distance_message',

        'proposal_question',
        'proposed_datetime',
        'proposed_location',
        'proposed_activity',
        'proposal_response',

        'intro_animation',
        'transition_style',
        'background_music',
    ];

    protected $casts = [
    'love_letter_content' => 'array',
    'sender_location' => 'array',
    'recipient_location' => 'array',
    'proposed_datetime' => 'datetime',

    'has_secret_question' => 'boolean',
    'has_love_letter' => 'boolean',
    'has_memories' => 'boolean',
    'has_gallery' => 'boolean',
    'has_map' => 'boolean',
    'has_proposal' => 'boolean',
];


    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function memories()
    {
        return $this->hasMany(GiftMemory::class);
    }

    public function photos()
    {
        return $this->hasMany(GiftPhoto::class);
    }
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

}
