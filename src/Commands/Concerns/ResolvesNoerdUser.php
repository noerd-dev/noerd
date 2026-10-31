<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use Noerd\Models\NoerdUser;

/**
 * The `{user}` argument of the account commands: an e-mail address or an id.
 */
trait ResolvesNoerdUser
{
    protected function resolveNoerdUser(string $identifier): ?NoerdUser
    {
        // E-mail first: an all-digit local part ("12345@example.com" — or a
        // numeric-only address in a test domain) would otherwise be read as an id.
        $user = NoerdUser::query()->where('email', $identifier)->first();

        if ($user) {
            return $user;
        }

        return is_numeric($identifier) ? NoerdUser::find((int) $identifier) : null;
    }
}
