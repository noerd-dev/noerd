<?php

namespace Noerd\Noerd\Traits;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;

trait HasEmailPreview
{
    public bool $showPreview = false;

    public bool $testEmailSending = false;

    abstract protected function getEmailData(): array;

    abstract protected function getEmailRateLimitPrefix(): string;

    abstract protected function getEmailViewName(): string;

    abstract public function getSampleEmailData(): array;

    #[Computed]
    public function canShowPreview(): bool
    {
        $data = $this->getEmailData();

        return ($data['send_email'] ?? false) && ! empty($data['email_body']);
    }

    #[Computed]
    public function testEmailRateLimitKey(): string
    {
        return 'test-email:' . $this->getEmailRateLimitPrefix() . ':' . auth()->id();
    }

    #[Computed]
    public function canSendTestEmail(): bool
    {
        return $this->canShowPreview && ! RateLimiter::tooManyAttempts($this->testEmailRateLimitKey, 1);
    }

    #[Computed]
    public function testEmailCooldownSeconds(): int
    {
        return RateLimiter::availableIn($this->testEmailRateLimitKey);
    }

    public function sendTestEmail(): void
    {
        if (! $this->canShowPreview) {
            return;
        }

        $key = $this->testEmailRateLimitKey;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            $this->js("alert('" . __('Bitte warten Sie :seconds Sekunden, bevor Sie eine weitere Test-E-Mail senden.', ['seconds' => $seconds]) . "')");

            return;
        }

        $this->testEmailSending = true;

        RateLimiter::hit($key, 30);

        $user = auth()->user();
        $emailData = $this->getEmailData();
        $sampleData = $this->getSampleEmailData();

        $subject = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $emailData['email_subject'] ?? __('Test-E-Mail')
        );

        $subject = '[TEST] ' . $subject;

        $emailBody = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $emailData['email_body'] ?? ''
        );

        $htmlContent = view($this->getEmailViewName(), [
            'emailBody' => $emailBody,
        ])->render();

        Mail::html($htmlContent, function ($message) use ($user, $subject) {
            $message->to($user->email)
                ->subject($subject);
        });

        $this->testEmailSending = false;

        $this->js("alert('" . __('Test-E-Mail wurde an :email gesendet.', ['email' => $user->email]) . "')");
    }

    #[Computed]
    public function previewEmailHtml(): string
    {
        if (! $this->canShowPreview) {
            return '';
        }

        $emailData = $this->getEmailData();
        $emailBody = $emailData['email_body'] ?? '';
        $sampleData = $this->getSampleEmailData();

        $processedBody = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $emailBody,
        );

        return view($this->getEmailViewName(), [
            'emailBody' => $processedBody,
        ])->render();
    }

    public function openPreview(): void
    {
        if ($this->canShowPreview) {
            $this->showPreview = true;
        }
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
    }
}
