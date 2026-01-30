<?php

namespace App\Models;

use App\Models\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    use UsesUuid;

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'status',
    ];
}
