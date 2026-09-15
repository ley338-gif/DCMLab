<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * W7 (ADR 0084): eine Mail pro faelliger Nutzerin/faelligem Nutzer,
 * verschickt von App\Console\Commands\ReviewSendReminders. Bewusst KEIN
 * ShouldQueue -- es laeuft kein Queue-Worker in diesem Deployment (nur
 * php-fpm im app-Container, siehe infra/docker-compose.yml), der Versand
 * geschieht synchron innerhalb des taeglichen Scheduler-Laufs.
 */
class ReviewReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public int $dueCount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Fällige Wissenskarten warten auf dich',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.review-reminder',
            with: [
                'reviewUrl' => url('/de/review'),
                'settingsUrl' => url('/de/settings/profile'),
            ],
        );
    }
}
