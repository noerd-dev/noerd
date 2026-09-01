<?php

namespace Noerd\Traits;

use Illuminate\Support\Str;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Support\LayoutFields;
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
 * - its layout always comes from the settings YAML — layout overrides never
 *   apply and there is no custom_attributes object manager,
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
        $this->initNoerdComponent(function (): void {
            $tenantId = TenantHelper::getSelectedTenantId();

            foreach ($this->settingsModelMap() as $property => $modelClass) {
                $model = $modelClass::firstOrNew(['tenant_id' => $tenantId]);

                $this->{$property} = collect($model->toArray())
                    ->except(['created_at', 'updated_at'])
                    ->toArray();
            }

            $this->pageLayout = StaticConfigHelper::getSettingsFields($this->componentName());
        });
    }

    public function store(): void
    {
        // Server-side guard, mirroring NoerdDetail::store(): the save button is
        // hidden for write-denied users, but store() stays reachable directly.
        if (!$this->canWriteObject()) {
            return;
        }

        $this->validateFromLayout();

        $this->persistSettings();

        $this->showSuccessIndicator = true;
    }

    /**
     * Object permission checks for a settings page cover EVERY declared
     * tenant-singleton model — NoerdPage's checks key off $detailModel, which a
     * settings page does not declare, and would therefore never restrict.
     */
    public function canReadObject(): bool
    {
        foreach ($this->settingsModelMap() as $modelClass) {
            if (!AccessHelper::canReadObject($modelClass)) {
                return false;
            }
        }

        return true;
    }

    public function canWriteObject(): bool
    {
        foreach ($this->settingsModelMap() as $modelClass) {
            if (!AccessHelper::canWriteObject($modelClass)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A settings page edits tenant singletons — saving is ALWAYS a write, even
     * when the row does not exist yet (persistSettings() creates it lazily).
     * NoerdPage's create/write split by $modelId must not apply here: a
     * settings page has no $modelId, so the base canSaveObject() would fall
     * through to the unrestricted create check.
     */
    public function canSaveObject(): bool
    {
        return $this->canWriteObject();
    }

    public function canDeleteObject(): bool
    {
        // Settings pages offer no delete, but the method stays consistent with
        // the other checks: NoerdPage's version keys off the absent
        // $detailModel and would therefore always allow.
        foreach ($this->settingsModelMap() as $modelClass) {
            if (!AccessHelper::canDeleteObject($modelClass)) {
                return false;
            }
        }

        return true;
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
            // A settings page is a pure form, so ONLY the keys its YAML declares
            // for this property may be written. Fails CLOSED: a property the
            // settings YAML does not bind writes nothing at all, rather than
            // falling through to an unfiltered mass assignment.
            $allowed = $allowedByProperty[$property] ?? [];
            if ($allowed === []) {
                continue;
            }

            $data = collect($this->{$property})
                ->except(['id', 'tenant_id', 'created_at', 'updated_at'])
                ->only($allowed);

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
        LayoutFields::walk(
            StaticConfigHelper::getSettingsFields($this->componentName())['fields'] ?? [],
            function (array $field) use (&$map): void {
                $name = $field['name'] ?? null;
                if (! is_string($name) || ! str_contains($name, '.')) {
                    return;
                }

                [$property, $rest] = explode('.', $name, 2);
                $map[$property][] = Str::before($rest, '.');
            },
        );

        return $map;
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
                $this->componentName(),
            ));
        }

        return $this->settingsModels;
    }
}
