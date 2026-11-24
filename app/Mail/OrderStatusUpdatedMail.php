<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $previousStatus,
        public string $newStatus,
        public ?string $note = null,
    ) {
        $this->order->loadMissing(['items']);
    }

    public function envelope(): Envelope
    {
        $statusLabel = ucfirst($this->newStatus);

        return new Envelope(
            subject: "Order {$this->order->order_number} Update - {$statusLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.status-updated',
        );
    }
}
