# Authentication

noerd ships its own auth stack and never claims the host application's default guard — "zero
intrusion into your application" applies to authentication too. At boot, `NoerdServiceProvider`
registers three things **at runtime** (your `config/auth.php` and `.env` are never written to):

| Key | Default value |
|-----|---------------|
| `auth.guards.noerd` | `['driver' => 'session', 'provider' => 'noerd_users']` |
| `auth.providers.noerd_users` | `['driver' => 'eloquent', 'model' => Noerd\Models\NoerdUser::class]` |
| `auth.passwords.noerd_users` | `['provider' => 'noerd_users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60]` |

**A key your application already defines always wins** — the runtime registration only fills in
keys that are absent. To override any part (a different session driver, your own broker table,
another user model), define the key in your `config/auth.php` and noerd will use it as-is.

## Configuration (`config/noerd.php`)

```php
'auth' => [
    'guard' => env('NOERD_AUTH_GUARD', 'noerd'),
    'model' => env('NOERD_AUTH_MODEL', Noerd\Models\NoerdUser::class),
    'provider' => 'noerd_users',
    'passwords' => 'noerd_users',
    'set_as_default' => env('NOERD_AUTH_DEFAULT', false),
],
```

- `guard` — the guard noerd registers and authenticates against. Setting `NOERD_AUTH_GUARD=web`
  restores the legacy behavior: the guard already exists, nothing is injected, and noerd runs on
  the host's default guard exactly as before.
- `model` — the Authenticatable backing the noerd user provider.
- `set_as_default` — when `true`, noerd also flips `auth.defaults.guard` / `auth.defaults.passwords`
  to the noerd guard at runtime. This is an escape hatch for hosts that still protect routes with
  the bare `auth` middleware alias (which resolves the default guard). Leave it `false` when noerd
  coexists with another auth stack (Nova, Breeze, ...) that owns the default guard.

## Routes & URL prefix

noerd's core screens live under a configurable URL prefix (default `noerd`, set via
`config('noerd.routes.prefix')` / `NOERD_ROUTE_PREFIX`): `/noerd/login`, `/noerd/forgot-password`,
`/noerd/reset-password/{token}`, `/noerd/user` (route name `noerd-user`), `/noerd/no-tenant`,
`/noerd/component-page/{componentName}`. Only the URLs carry the prefix — the route names are
stable and unaffected by a prefix change. The `/setup` area keeps its own prefix, and the apps
dashboard stays at `/noerd-apps` (already namespaced, and the address the installer prints).

The auth route names are namespaced (`noerd.login`, `noerd.password.request`,
`noerd.password.reset`) and the package registers **no** route named `login`, `dashboard`,
`profile`, `password.request` or `password.reset` — a coexisting starter kit keeps those names to
itself and `php artisan route:cache` never sees a duplicate.

`/login` still works on a plain noerd installation: an **unnamed** redirect to the prefixed login
route. Because it is unnamed and registered before the host's routes, a starter kit that claims the
`/login` URI simply overrides it — the redirect only serves hosts without their own login.

## Route middleware groups

`NoerdServiceProvider` registers two shared route middleware groups:

```php
$router->middlewareGroup('noerd', ['web', NoerdAuthenticate::class . ':noerd', 'verified']);
$router->middlewareGroup('noerd-guest', ['web', NoerdRedirectIfAuthenticated::class . ':noerd']);
```

Every noerd-based module protects its routes with `['noerd']` (plus module-specific aliases such
as `app-access:crm`) instead of `['web', 'auth', 'verified']`. The `noerd:module` scaffolder
generates routes with the `noerd` group.

`Noerd\Middleware\NoerdAuthenticate` extends Laravel's `Authenticate` and pins the guest redirect
to `route('noerd.login')`; `Noerd\Middleware\NoerdRedirectIfAuthenticated` extends
`RedirectIfAuthenticated` and pins the authenticated redirect to `route('noerd-apps')`. Neither the
host's `auth`/`guest` middleware aliases nor globally registered `redirectUsing()` callbacks (e.g.
from a starter kit's `bootstrap/app.php`) apply to noerd routes — the two stacks never redirect
into each other.

The authenticate middleware also does the heavy lifting for guard propagation: it calls
`Auth::shouldUse('noerd')` for the matched guard, which makes `noerd` the default guard **for the
remainder of that request** — every guard-less `auth()->user()`, `@auth`, `$request->user()` and
Gate check inside a noerd route resolves the noerd guard without any code change. Livewire
re-applies the route's persistent middleware on every component update (`NoerdAuthenticate` is
registered in Livewire's persistent-middleware list), so this holds for Livewire actions too.

## The `NoerdAuth` helper

Framework internals that may run **outside** a noerd route (global middleware, tenant scoping on
models a host route touches, queued jobs, console commands) must not rely on the request's default
guard. They resolve the user explicitly through `Noerd\Helpers\NoerdAuth`:

```php
use Noerd\Helpers\NoerdAuth;

NoerdAuth::guardName();   // 'noerd' (configured guard)
NoerdAuth::guard();       // StatefulGuard instance
NoerdAuth::user();        // ?Authenticatable
NoerdAuth::id();          // int|string|null
NoerdAuth::check();       // bool
NoerdAuth::broker();      // PasswordBroker for the noerd broker
```

`TenantScope`, `BelongsToTenant`, `TenantHelper`, the noerd middleware and the noerd-plus
permission resolvers all resolve through `NoerdAuth` — a host guard's user can never influence
(or bypass) tenant scoping or noerd permissions. Module code that only ever runs inside noerd
routes may keep using plain `auth()->user()`.

## Coexistence with a host auth stack (e.g. Laravel Nova)

A host that already owns the `web` guard (Nova admin users in a `users` table, Breeze, a custom
login) needs no special setup:

1. Install noerd as usual — `config/auth.php` keeps the host's `users` provider and default guard.
2. Keep `NOERD_AUTH_DEFAULT` unset (or `false`).
3. noerd users live in `noerd_users` and log in via noerd's `/noerd/login` against the `noerd`
   guard; host users keep logging in via their own stack (e.g. their own `/login`) against `web`.

Both guards share the session cookie but store independent login keys
(`login_noerd_<hash>` vs `login_web_<hash>`) and separate remember-me cookies — a user can be
logged in to both sides at once. noerd's logout only ends the noerd session and clears noerd's
session state (`noerd.*` keys); the host session survives.

**Caveat — packages with their own guard list:** packages that resolve the acting user from a
configured guard list (e.g. `owen-it/laravel-auditing` via `config/audit.php` → `user.guards`)
must have `noerd` added to that list, or actions performed by noerd users are attributed to no one.

**Caveat — password reset tokens:** the noerd broker uses the standard `password_reset_tokens`
table, keyed by email. If a host user and a noerd user share the same email address, requesting a
reset on one side invalidates the other side's pending token. Define `auth.passwords.noerd_users`
with your own `table` in `config/auth.php` to separate them.

## Upgrading existing installations

Before this guard existed, `noerd:install` wrote `AUTH_MODEL=Noerd\Models\NoerdUser` to `.env` and
noerd ran on the host's default `web` guard. After updating:

- The default `guard => 'noerd'` takes effect immediately. Sessions are stored per guard name, so
  every user is logged out **once** and simply logs in again — no data changes.
- Remember-me cookies are also per guard name and are re-issued on the next "remember" login.
- A stale `AUTH_MODEL` entry in `.env` is harmless and can be removed.
- To defer the switch, set `NOERD_AUTH_GUARD=web` — this restores the previous behavior unchanged
  (the legacy `.env` `AUTH_MODEL` hijack keeps working where the host's `config/auth.php` reads it).

## Testing

The test environments point `auth.defaults.guard` at the noerd guard (see
`app-modules/noerd/tests/TestCase.php` and the host `tests/TestCase.php`), so guard-less
`actingAs($user)` calls authenticate on the same guard the `noerd` route group checks. Mechanics
are covered by `app-modules/noerd/tests/Feature/NoerdGuardTest.php` — including the coexistence
scenario (a second guard's user must never affect tenant scoping) and logout isolation.
