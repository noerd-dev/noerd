<?php

declare(strict_types=1);

namespace Noerd\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The "send test email" preview of HasEmailPreview. Queued so the Livewire
 * response never blocks on SMTP; on the default sync queue driver the send
 * still happens inline.
 */
class EmailPreviewTestMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $htmlContent,
        private readonly string $subjectLine,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->htmlContent);
    }
}
