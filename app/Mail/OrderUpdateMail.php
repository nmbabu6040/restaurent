<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public string $kind = 'status') {}

    public function envelope(): Envelope
    {
        $name = setting('name', config('app.name'));

        return new Envelope(
            subject: $this->kind === 'placed'
                ? __('orders.mail.subject_placed', ['name' => $name, 'reference' => $this->order->reference])
                : __('orders.mail.subject_status', ['reference' => $this->order->reference, 'status' => $this->order->status->label()]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order');
    }
}
