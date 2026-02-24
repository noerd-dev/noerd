# Creating Modules

Using modules is completely optional. The application works perfectly fine without any modules. 

The module approach is very inspired by https://github.com/InterNACHI/modular

Use the `noerd:module` Artisan command to create a new module with complete directory structure.

## Quick Start

```bash
php artisan noerd:module
```

The command will ask for:
1. **Module name** (e.g., `inventory`)
2. **Main model name** (e.g., `item`)


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
| `resources/views/components/` | Volt components (⚡ prefix) |
| `resources/lang/` | Translations (JSON) |
| `routes/` | Route definitions |
| `src/Models/` | Eloquent models |
| `src/Providers/` | ServiceProvider |
| `tests/` | Pest tests |

## Next Steps

- [Lists](03-lists.md) - Customize list views
- [Detail Views](04-models.md) - Customize detail forms
- [YAML Configuration](yaml-configuration.md) - Full YAML reference
