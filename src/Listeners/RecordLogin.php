<?php

declare(strict_types=1);

namespace Noerd\Listeners;

use Illuminate\Auth\Events\Login;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\NoerdLogin;

/**
 * Persists every successful noerd login. The listener hangs off the framework
 * Login event, so it covers all entry points at once — the login form, magic
 * links and impersonation — without any module writing the row itself.
 */
class RecordLogin
{
    public function handle(Login $event): void
    {
        // The Login event fires for every guard — only record noerd logins.
        if ($event->guard !== NoerdAuth::guardName()) {
            return;
        }

        $impersonatorId = session('impersonating_from');

        NoerdLogin::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'impersonated_by_id' => $impersonatorId ? (int) $impersonatorId : null,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 1000) ?: null,
            'remember' => (bool) $event->remember,
            'created_at' => now(),
        ]);
    }
}
