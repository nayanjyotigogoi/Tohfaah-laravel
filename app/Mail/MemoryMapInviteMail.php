<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MemoryMapInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $map;
    public $inviter;

    public function __construct($map, $inviter)
    {
        $this->map = $map;
        $this->inviter = $inviter;
    }

    public function build()
    {
        return $this->subject('You’ve been invited to collaborate on a Memory Map 💌')
            ->markdown('emails.memory-map-invite');
    }
}