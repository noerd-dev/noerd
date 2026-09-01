# Create an App

Now that at least one user and one tenant have been set up, the first app can be created, which we can assign to a tenant.

![Noerd Example App](/assets/apps.png "Navigation")

```bash
php artisan noerd:create-app
```

The command asks for:
1. **App Title** - Display name (e.g., "Customer Management")
2. **App Name** - Unique identifier, uppercase (e.g., "CRM")
3. **Icon** - Heroicon name (searchable)

Every app ships with its own dashboard, so there is nothing to ask about the app's route: the
command runs `noerd:make-dashboard` for the new app and stores the generated route
(`{app}.dashboard`, e.g. `crm.dashboard`) as the app's main route. The scaffold consists of

- `resources/views/components/{app}-dashboard.blade.php` — the dashboard page (`NoerdPage`)
- a `Route::livewire('{app}', '{app}-dashboard')->name('{app}.dashboard')` entry in `routes/web.php`,
  wrapped in the `noerd` + `app-access:{app}` middleware group
- `app-configs/{app}/navigation.yml` with a first block that links the dashboard (an existing
  navigation only gets the dashboard entry inserted)

For non-interactive use (e.g. in install scripts), every prompt has an option:

```bash
php artisan noerd:create-app --title="Customer Management" --name=CRM \
    --icon=users --active=1
```

Pass `--route=` only when the app tile should open an existing route instead — no dashboard is
generated then:

```bash
php artisan noerd:create-app --title="Customer Management" --name=CRM \
    --icon=users --route=crm.index
```

Within that command, you can assign that app to one or more tenants. You can also do that later with another Artisan command:

```bash
php artisan noerd:assign-apps-to-tenant
# or non-interactive
php artisan noerd:assign-apps-to-tenant --tenant-id=1
```

If you visit /noerd-apps again, you should now see your created app in the sidebar; opening it
shows the generated dashboard.

## Next Steps

Continue with [Create Navigation](navigation.md) to extend the generated navigation with your
app's lists and pages.
