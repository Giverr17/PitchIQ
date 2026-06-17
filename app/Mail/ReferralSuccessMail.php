<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReferralSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $referrer,
        public string $friendName,
        public int $reward
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You earned 20 tokens! 🪙');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.referral-success');
    }
}