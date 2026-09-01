# Example Application

The fastest way to see every core concept in action is the **Demo app** that ships with the
package. `php artisan noerd:install` offers it as the last step; it can also be installed at any
time with:

```bash
php artisan noerd:demo --migrate --seed
```

(see [noerd:demo](artisan-commands.md#noerddemo) — non-interactive runs need both flags).

## What gets installed

The command copies the demo files from the package's `demo/` folder straight into your project —
they are yours to edit and serve as a reference for your own apps:

| Target | Content |
|--------|---------|
| `app/Models/DemoCustomer.php`, `DemoCategory.php`, `DemoTag.php` | Eloquent models using `BelongsToTenant` and `$guarded = []`; `DemoCustomer` belongs to a category, has many tags (`belongsToMany`) and carries a `custom_attributes` cast |
| `database/migrations/*_create_demo_*` | Tables `demo_customers`, `demo_categories`, `demo_tags` and the `demo_customer_demo_tag` pivot |
| `database/seeders/DemoSeeder.php` | Sample categories, tags and customers for every tenant |
| `resources/views/components/demo-*-list.blade.php` / `demo-*-detail.blade.php` | Slim `NoerdList` / `NoerdDetail` single-file components |
| `app-configs/demo/lists/*.yml`, `details/*.yml`, `navigation.yml` | The YAML configuration of the three lists and details plus the app navigation |
| `routes/web.php` | A `Route::group(['middleware' => ['noerd', 'app-access:demo']])` block with the list and detail routes |

The app is registered as the tenant app `DEMO` (main route `demo-customers`) and assigned to every
tenant, so it appears on `/noerd-apps` right away.

## What it demonstrates

**Slim components.** `demo-categories-list` / `demo-category-detail` are the minimal shape: the
list declares `$listModel`, `$detailRoute = 'demo-category.detail'` and `$detailComponent`, the
detail declares `$detailModel` and `$detailPrimary` — everything else comes from the traits and the
YAML (see [List View](list-view.md), [Detail View](detail-view.md)).

**Route modals.** Rows open the record through the named route (`demo-customer.detail`,
`demo-category.detail`, `demo-tag.detail`), which rewrites the browser URL; the component key stays
the fallback. The list's "New …" action and the navigation's `newRoute:` / `newComponent:` pair use
the same routes (see [Modal System](modal.md), [Navigation](navigation.md)).

**Field types.** `demo-customer-detail.yml` is a showcase of the detail YAML: two `tabs`, a
`required` text field, `select` fields with inline `options` (rendered as translated badges in the
list) and with `optionsMethod`, `email`, `phone`, `currency`, `date`, `time`, `colorHex`,
`checkbox`, `textarea`, `richText` and a `belongsToMany` field bound to a component property
(`tagIds`) — see [Field Types](field-types.md).

**Custom `store()`.** The customer detail overrides `store()` for the one thing YAML cannot
express — syncing the many-to-many tags — and still runs through the generic pieces:
`canSaveObject()`, `validateFromLayout()`, `writableDetailData()` and `finishStore()`.

**List configuration.** `demo-customers-list.yml` shows `defaultSort`, a route-based header action
and typed columns (`currency`, `date`, `bool`).

**Tenancy and access.** Every table has `tenant_id`, the models are tenant-scoped, and the routes
are protected by `app-access:demo` (see [Authentication](auth.md), [Permissions](permissions.md)).

## Further reading

- [Create an App](create-app.md) — register your own app the same way
- [Creating Modules](creating-modules.md) — package an app as a reusable module
