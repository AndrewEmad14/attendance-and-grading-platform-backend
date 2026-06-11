<?php

// app/Mail/EmailChangedNotification.php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailChangedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $oldEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your account email has been changed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-changed',
        );
    }
}
