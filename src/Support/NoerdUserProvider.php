<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Noerd\Helpers\NoerdAuth;
use SensitiveParameter;

/**
 * The user provider of the noerd guard. While the password login is switched
 * off (`noerd.features.password_login`), a "remember me" cookie no longer
 * signs anybody in: it was handed out by the password form, and a person who
 * may only sign in through a provider must pass that provider again.
 */
class NoerdUserProvider extends EloquentUserProvider
{
    public const DRIVER = 'noerd';

    /**
     * @param  mixed  $identifier
     * @param  string  $token
     */
    public function retrieveByToken($identifier, #[SensitiveParameter] $token): ?Authenticatable
    {
        if (! NoerdAuth::passwordLoginEnabled()) {
            return null;
        }

        return parent::retrieveByToken($identifier, $token);
    }
}
