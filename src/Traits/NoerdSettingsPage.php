<?php

namespace Noerd\Traits;

use Illuminate\Support\Str;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use RuntimeException;

/**
 * Trait for settings pages: tenant-singleton forms configured EXCLUSIVELY by a
 * settings YAML (settings/{name}.yml). A settings page differs from a detail:
 *
 * - it edits one row per tenant (keyed by tenant_id) per declared model — no
 *   $modelId, no $detailPrimary URL alias, no delete path,
 * - it may edit SEVERAL tenant-singleton models at once, declared as
 *   `public array $settingsModels = ['detailData' => Model::class, ...];`
 *   where each key is the public array property the YAML fields bind to,
 * - its layout always comes from the settings YAML — the noerd-pro layout
 *   manager never applies and there is no custom_attributes object manager,
 * - it always renders in the built-in `settings` theme (fields stacked
 *   vertically, full width — no grid).
 *
 * Custom validation: override store(), add the checks, then end with
 * `$this->persistSettings(); $this->showSuccessIndicator = true;`.
 */
trait NoerdSettingsPage
{
    use NoerdDetail;

    public function mount(): void
    {
        $this->initSettings();
    }

    public function initSettings(): void
    {
        if ($this->prepareRoutedModal()) {
            return;
        }

        $tenantId = TenantHelper::getSelectedTenantId();

        foreach ($this->settingsModelMap() as $property => $modelClass) {
            $model = $modelClass::firstOrNew(['tenant_id' => $tenantId]);

            $this->{$property} = collect($model->toArray())
                ->except(['created_at', 'updated_at'])
                ->toArray();
        }

        $this->pageLayout = StaticConfigHelper::getSettingsFields($this->getName());
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $this->persistSettings();

        $this->showSuccessIndicator = true;
    }

    /**
     * Persist every declared settings model as the tenant's singleton row.
     * Kept separate so custom store() overrides reuse it as their tail.
     */
    protected function persistSettings(): void
    {
        $tenantId = TenantHelper::getSelectedTenantId();
        $allowedByProperty = $this->settingsWritableKeys();

        foreach ($this->settingsModelMap() as $property => $modelClass) {
            $data = collect($this->{$property})
                ->except(['id', 'tenant_id', 'created_at', 'updated_at']);

            // A settings page is a pure form, so only the keys its YAML declares
            // may be written — never an extra column injected into the client array.
            $allowed = $allowedByProperty[$property] ?? [];
            if ($allowed !== []) {
                $data = $data->only($allowed);
            }

            $modelClass::updateOrCreate(['tenant_id' => $tenantId], $data->toArray());
        }
    }

    /**
     * Allowed top-level keys per bound property, read from the settings YAML on
     * disk (not the client-controlled $pageLayout).
     *
     * @return array<string, array<int, string>>
     */
    protected function settingsWritableKeys(): array
    {
        $map = [];
        $this->collectSettingsWritableKeys(StaticConfigHelper::getSettingsFields($this->getName())['fields'] ?? [], $map);

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, array<int, string>>  $map
     */
    protected function collectSettingsWritableKeys(array $fields, array &$map): void
    {
        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'block') {
                $this->collectSettingsWritableKeys($field['fields'] ?? [], $map);

                continue;
            }

            $name = $field['name'] ?? null;
            if (! is_string($name) || ! str_contains($name, '.')) {
                continue;
            }

            [$property, $rest] = explode('.', $name, 2);
            $map[$property][] = Str::before($rest, '.');
        }
    }

    /**
     * The declared settings models. Every key must be a public array property on
     * the component ('detailData' is provided by NoerdPage; extra keys are
     * declared by the component itself).
     *
     * @return array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    protected function settingsModelMap(): array
    {
        if (!isset($this->settingsModels) || $this->settingsModels === []) {
            throw new RuntimeException(sprintf(
                'Settings page [%s] must declare its tenant-singleton models: '
                . "`public array \$settingsModels = ['detailData' => Model::class];`.",
                $this->getName(),
            ));
        }

        return $this->settingsModels;
    }
}
