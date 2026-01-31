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
4. **Route** - Main route name (e.g., "crm.index")

Within that command, you can assign that app to one or more tenants. You can also do that later with another Artisan command.

```bash
php artisan noerd:assign-apps-to-tenant
```

If you visit /noerd-home again, you should now see your created app in the sidebar.

## Next Steps

Continue with [Create Navigation](navigation.md) to define your app's navigation structure.
