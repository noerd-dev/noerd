<?php

declare(strict_types=1);

namespace Noerd\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person that an administrator gave their EXISTING account access to
 * another tenant. The access applies at once — the mail only informs, there is
 * nothing to accept. A brand-new account gets the password link instead.
 */
class AddedToTenantNotification extends Notification
{
    public function __construct(public string $tenantName, public string $addedBy) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(__('You were added to :tenant', ['tenant' => $this->tenantName]))
            ->line(__(':name added you to :tenant.', ['name' => $this->addedBy, 'tenant' => $this->tenantName]))
            ->line(__('Sign in with your existing account and switch to the tenant in the top bar.'))
            ->action(__('Log in'), url(route('noerd.login', [], false)));
    }
}
