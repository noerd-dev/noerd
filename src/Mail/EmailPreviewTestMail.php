<?php

namespace Noerd\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
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

    public function build(): self
    {
        return $this->subject($this->subjectLine)->html($this->htmlContent);
    }
}
