<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The test email an admin can send from Site settings to check the email account works. */
class SettingsTestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'TestaCBT email test');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>This is a test from TestaCBT.</p><p>If you can read it, the email account in Site settings works, and password reset links will reach your students.</p>');
    }
}
