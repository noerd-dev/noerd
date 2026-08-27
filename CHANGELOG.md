# Changelog

## Unreleased

### Fixed

- `noerd:demo` appended a demo route block to `routes/web.php` using
  `Route::group(['middleware' => ['auth', 'verified', 'web']], ...)`. The bare `auth` middleware
  checks the default `web` guard while noerd authenticates via its own `noerd` guard, so a logged-in
  user was treated as a guest on `/demo-customers` and bounced between `/login` and the dashboard
  redirect in an endless loop (`ERR_TOO_MANY_REDIRECTS`). The generated block now uses the package
  middleware group `['noerd']`, consistent with `docs/auth.md` and the `noerd:module` route stub.

  **Upgrade note for existing installations:** the broken line is already written into your
  project's `routes/web.php` and is not fixed automatically. Change the demo route block manually
  from

  ```php
  Route::group(['middleware' => ['auth', 'verified', 'web']], function (): void {
  ```

  to

  ```php
  Route::group(['middleware' => ['noerd']], function (): void {
  ```

### Changed

- `noerd:install` now asks whether to install the Demo App at the end of the installation instead
  of upfront, and no longer prints the auth-guard notice and application URL after finishing.
