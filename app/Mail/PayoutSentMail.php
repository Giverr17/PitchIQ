<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayoutSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public int $amount,
        public string $phone,
        public string $tournamentName,
        public int $matchday,
        public int $position
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You won airtime on PitchIQ! 🏆");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payout-sent');
    }
}