<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Exception;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Noerd\Facades\Noerd;
use Noerd\Helpers\NoerdAuth;
use Noerd\Mail\EmailPreviewTestMail;

trait HasEmailPreview
{
    abstract protected function getEmailData(): array;

    abstract protected function getEmailViewName(): string;

    abstract protected function getEmailRateLimitPrefix(): string;

    abstract public function getSampleEmailData(): array;

    #[Computed]
    public function canShowPreview(): bool
    {
        $data = $this->getEmailData();

        return ! empty($data['send_email']) && ! empty($data['email_body'] ?? '');
    }

    #[Computed]
    public function canSendTestEmail(): bool
    {
        return ! Cache::has($this->getTestEmailCacheKey());
    }

    #[Computed]
    public function testEmailCooldownSeconds(): int
    {
        $remaining = Cache::get($this->getTestEmailCacheKey());

        if (! $remaining) {
            return 0;
        }

        return max(0, (int) ceil($remaining - now()->timestamp));
    }

    public function openPreview(): void
    {
        $data = $this->getEmailData();

        Noerd::modal('noerd::email-preview-modal', [
            'emailSubject' => $data['email_subject'] ?? '',
            'sampleData' => $this->getSampleEmailData(),
            'previewHtml' => $this->renderEmailPreview(),
        ]);
    }

    public function sendTestEmail(): void
    {
        if (! $this->canSendTestEmail) {
            return;
        }

        $user = NoerdAuth::user();
        $email = $user->email;

        if (! $email) {
            return;
        }

        $html = $this->renderEmailPreview();
        $data = $this->getEmailData();
        $sampleData = $this->getSampleEmailData();
        $subject = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $data['email_subject'] ?? 'Test Email',
        );

        // Queued: a synchronous SMTP send would block the Livewire response.
        // On the default sync queue driver the behavior is unchanged.
        Mail::to($email)->queue(new EmailPreviewTestMail($html, $subject));

        $cooldown = 60;
        Cache::put($this->getTestEmailCacheKey(), now()->timestamp + $cooldown, $cooldown);
    }

    protected function renderEmailPreview(): string
    {
        $data = $this->getEmailData();
        $sampleData = $this->getSampleEmailData();

        $body = $data['email_body'] ?? '';
        $body = str_replace(array_keys($sampleData), array_values($sampleData), $body);

        try {
            return (string) app(Markdown::class)->render(
                $this->getEmailViewName(),
                ['emailBody' => new HtmlString($body), 'data' => $data],
            );
        } catch (Exception) {
            return nl2br(e($body));
        }
    }

    protected function getTestEmailCacheKey(): string
    {
        return 'test-email-cooldown:' . $this->getEmailRateLimitPrefix() . ':' . NoerdAuth::id();
    }
}
