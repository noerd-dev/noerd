<?php

declare(strict_types=1);

namespace Noerd\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * Password reset notification for noerd users. The framework notification
 * builds its link from route('password.reset') — a name noerd no longer
 * claims, so the URL must come from noerd's own route. Deliberately ignores
 * a global ResetPassword::createUrlUsing() callback: that hook belongs to a
 * coexisting host auth stack, not to noerd users.
 */
class NoerdResetPassword extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        return url(route('noerd.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
