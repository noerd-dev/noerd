<?php

use Livewire\Component;
use Noerd\Traits\HasEmailPreview;

/**
 * Test-only component (EmailPreviewTest): the smallest possible host for the
 * HasEmailPreview trait, so its cooldown, cache key and fallback rendering can
 * be proven without any module's settings screen.
 */
new class extends Component {
    use HasEmailPreview;

    /** Deliberately not a registered view, so renderEmailPreview() takes its fallback path. */
    public string $emailView = 'noerd-test::zz-no-such-email-view';

    public function previewHtml(): string
    {
        return $this->renderEmailPreview();
    }

    public function getSampleEmailData(): array
    {
        return ['{{field:name}}' => 'Jane'];
    }

    protected function getEmailData(): array
    {
        return [
            'send_email' => true,
            'email_subject' => 'Zz Subject for {{field:name}}',
            'email_body' => "Zz body for {{field:name}}\n<script>alert(1)</script>",
        ];
    }

    protected function getEmailViewName(): string
    {
        return $this->emailView;
    }

    protected function getEmailRateLimitPrefix(): string
    {
        return 'zz-email-preview';
    }
}; ?>

<div></div>
