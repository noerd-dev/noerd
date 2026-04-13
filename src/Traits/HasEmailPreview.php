<?php

namespace Noerd\Traits;

use Exception;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

trait HasEmailPreview
{
    public bool $showPreview = false;

    public string $previewEmailHtml = '';

    abstract protected function getEmailData(): array;

    abstract protected function getEmailViewName(): string;

    abstract protected function getEmailRateLimitPrefix(): string;

    abstract public function getSampleEmailData(): array;

    public function getCanShowPreviewProperty(): bool
    {
        $data = $this->getEmailData();

        return ! empty($data['send_email']) && ! empty($data['email_body'] ?? '');
    }

    public function getCanSendTestEmailProperty(): bool
    {
        return ! Cache::has($this->getTestEmailCacheKey());
    }

    public function getTestEmailCooldownSecondsProperty(): int
    {
        $remaining = Cache::get($this->getTestEmailCacheKey());

        if (! $remaining) {
            return 0;
        }

        return max(0, (int) ceil($remaining - now()->timestamp));
    }

    public function openPreview(): void
    {
        $this->previewEmailHtml = $this->renderEmailPreview();
        $this->showPreview = true;
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
    }

    public function sendTestEmail(): void
    {
        if (! $this->canSendTestEmail) {
            return;
        }

        $user = auth()->user();
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

        Mail::html($html, function ($message) use ($email, $subject): void {
            $message->to($email)->subject($subject);
        });

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
            return Blade::render(
                view($this->getEmailViewName())->with(['body' => new HtmlString($body), 'data' => $data])->render(),
            );
        } catch (Exception) {
            return nl2br(e($body));
        }
    }

    protected function getTestEmailCacheKey(): string
    {
        return 'test-email-cooldown:' . $this->getEmailRateLimitPrefix() . ':' . auth()->id();
    }
}
