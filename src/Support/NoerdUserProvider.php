<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\NoerdUser;
use SensitiveParameter;

/**
 * The user provider of the noerd guard. While the password login is switched
 * off (`noerd.features.password_login`), a "remember me" cookie no longer
 * signs anybody in: it was handed out by the password form, and a person who
 * may only sign in through a provider must pass that provider again.
 *
 * Credentials carrying `login` name the account by its e-mail address or its
 * username (NoerdUser::findForLogin()).
 */
class NoerdUserProvider extends EloquentUserProvider
{
    public const DRIVER = 'noerd';

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(#[SensitiveParameter] array $credentials): ?Authenticatable
    {
        if (array_key_exists('login', $credentials)) {
            return is_string($credentials['login']) ? NoerdUser::findForLogin($credentials['login']) : null;
        }

        return parent::retrieveByCredentials($credentials);
    }

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
