<?php

declare(strict_types=1);

namespace Noerd\Enums;

/**
 * The built-in user profiles. A profile is the per-tenant BASELINE of what a
 * user may do — assigned via users_tenants.profile_key, exactly one per user
 * per tenant. Profiles are a fixed technical concept: there is no profile
 * CRUD. Modules may register ADDITIONAL profiles (key + label) through the
 * ProfileRegistry; their semantics come from the authorization gates (see
 * AccessHelper) — the core treats unknown keys like User.
 */
enum Profile: string
{
    /** Tenant administration: setup access and bypassing every permission check. */
    case Admin = 'ADMIN';

    /** Default profile: full access, new apps and objects are usable immediately. */
    case User = 'USER';

    /** Like User, but restricted to reading (see AccessHelper's profile baseline). */
    case ReadOnly = 'READ_ONLY';

    /** The display label, translated into the active language. */
    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Admin'),
            self::User => __('User'),
            self::ReadOnly => __('Read Only'),
        };
    }
}
