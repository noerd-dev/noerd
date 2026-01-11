# Creating Modules

Use the `noerd:module` Artisan command to create a new module with complete directory structure.

## Quick Start

```bash
php artisan noerd:module
```

The command will ask for:
1. **Module name** (e.g., `inventory`)
2. **Main model name** (e.g., `item`)

## What Gets Created

```
app-modules/{module-name}/
├── app-contents/{module-name}/
│   ├── lists/{models}-list.yml
│   ├── details/{model}-detail.yml
│   └── navigation.yml
├── database/
│   └── migrations/
├── resources/
│   ├── views/livewire/
│   │   ├── {models}-list.blade.php
│   │   └── {model}-detail.blade.php
│   └── lang/
│       ├── de.json
│       └── en.json
├── routes/{module-name}-routes.php
├── src/
│   ├── Models/{Model}.php
│   └── Providers/{ModuleName}ServiceProvider.php
├── tests/
└── composer.json
```

## Next Steps

After the command completes:

```bash
# 1. Register the module
composer update noerd/{module-name}

# 2. Run migrations
php artisan migrate

# 3. Create TenantApp
php artisan noerd:create-app
# Name: {ModuleName}
# Route: {module-name}.index
```

## Customization

After creation, customize the module:

- **Add fields**: Edit `details/{model}-detail.yml`
- **Add columns**: Edit `lists/{models}-list.yml`
- **Add migrations**: Create in `database/migrations/`
- **Add relationships**: Edit model in `src/Models/`
- **Add routes**: Edit `routes/{module-name}-routes.php`

## Module Structure Reference

| Directory | Purpose |
|-----------|---------|
| `app-contents/` | YAML configurations |
| `database/migrations/` | Database migrations |
| `resources/views/livewire/` | Volt components |
| `resources/lang/` | Translations (JSON) |
| `routes/` | Route definitions |
| `src/Models/` | Eloquent models |
| `src/Providers/` | ServiceProvider |
| `tests/` | Pest tests |

## Next Steps

- [Lists](03-lists.md) - Customize list views
- [Detail Views](04-models.md) - Customize detail forms
- [YAML Configuration](yaml-configuration.md) - Full YAML reference
