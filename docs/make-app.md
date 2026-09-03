# Create an App

Now that at least one user and one tenant have been set up, the first app can be created, which we can assign to a tenant.

![Noerd Example App](/assets/apps.png "Navigation")

```bash
php artisan noerd:make-app
```

The command first asks **where** the app should live:

- **Project** — the app lives in the application itself (`resources/views/components/`,
  `routes/web.php`, `app-configs/{app}/`). The right choice for a single project.
- **Module** — the app becomes its own Composer package under `app-modules/{app}/` with the
  module boilerplate (see [Creating Modules](creating-modules.md)). The right choice when the app
  is meant to be reused in other projects or maintained in its own repository.

Then it asks for:
1. **App Title** - Display name (e.g., "Inventory Management")
2. **App Name** - Unique identifier; normalized to uppercase with underscores (e.g., "INVENTORY")
3. **Icon** - Heroicon name (searchable); stored as `heroicon:outline:{name}`

An app starts with its dashboard only. Record types (list + detail) are added afterwards with
`noerd:make-resource` — for a project app and for a module alike.

## Project

Every app ships with its own dashboard, so there is nothing to ask about the app's route: the
command runs `noerd:make-dashboard` for the new app and stores the generated route
(`{app}.dashboard`, e.g. `inventory.dashboard`) as the app's main route. The scaffold consists of

- `resources/views/components/{app}-dashboard.blade.php` — the dashboard page (`NoerdPage`)
- a `Route::livewire('{app}', '{app}-dashboard')->name('{app}.dashboard')` entry in `routes/web.php`,
  wrapped in the `noerd` + `app-access:{app}` middleware group
- `app-configs/{app}/navigation.yml` with a first block that links the dashboard (an existing
  navigation only gets the dashboard entry inserted)

Within that command, you can assign that app to one or more tenants. You can also do that later with another Artisan command:

```bash
php artisan noerd:assign-apps-to-tenant
# or non-interactive
php artisan noerd:assign-apps-to-tenant --tenant-id=1
```

## Module

The command hands the scaffold to `noerd:module`: the app name becomes the module key (`MY_APP` →
`app-modules/my-app`), the title and the heroicon go into the generated install command, and the
module gets its dashboard, the dashboard route and navigation, translations, the install/update
commands and the agent guidelines — everything [Creating Modules](creating-modules.md) describes.
The root `composer.json` gets a `noerd/{app}` requirement.

Everything after the scaffold runs on its own: Composer registers the package, then the generated
`noerd:install-{app}` command runs in its silent scaffold mode — it publishes the YAML configs and
registers the app (its name is the uppercase module key, `MY-APP`), and the only question left is
which tenants get the app. Migrations and the frontend build are not run; the command closes with
an "is ready" callout naming the next step:

```bash
php artisan noerd:make-resource {Model} --app=my-app
```

`--route` is not available in module mode (the module ships its own dashboard route, `{app}`), and
`--active` is ignored. The install command stays re-runnable (`noerd:install-{app}`, then
`noerd:update-{app}` for later YAML updates).

## Non-interactive use

For install scripts, every prompt has an option (the full option table is in
[Artisan Commands](artisan-commands.md#noerdmake-app)):

```bash
php artisan noerd:make-app --title="Inventory Management" --name=INVENTORY \
    --icon=heroicon:outline:users --active=1
```

Pass `--route=` only when the app tile should open an existing route instead — no dashboard is
generated then:

```bash
php artisan noerd:make-app --title="Inventory Management" --name=INVENTORY \
    --icon=heroicon:outline:users --route=inventory.index
```

Module mode needs `--module`; a scripted run scaffolds the module and prints the Composer and
install steps instead of running them:

```bash
php artisan noerd:make-app --title="Inventory Management" --name=INVENTORY \
    --icon=heroicon:outline:users --module
```

If you visit /noerd-apps again, you should now see your created app in the sidebar; opening it
shows the generated dashboard.

## Next Steps

Continue with [Create Navigation](navigation.md) to extend the generated navigation with your
app's lists and pages.
