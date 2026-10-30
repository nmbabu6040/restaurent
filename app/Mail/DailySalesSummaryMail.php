<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailySalesSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $day,
        public array $summary,
        public array $previous,
        public array $top,
        public array $statuses,
        public array $profit,
        public int $lowStock,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Sales {$this->day}: " . money($this->summary['revenue']) . ", {$this->summary['orders']} orders");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.daily-summary');
    }
}
