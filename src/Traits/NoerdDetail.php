<?php

namespace Noerd\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Services\PicklistRegistry;
use Noerd\Support\LayoutDefaults;
use Noerd\Support\LayoutFields;
use Noerd\Support\RelationFormSync;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Trait for `*-detail` components: the model form on top of the NoerdPage base.
 * A detail loads its MANDATORY detail YAML (details/{name}.yml), binds the record
 * into $detailData and persists it via store(). All page chrome (tabs, modal
 * lifecycle, quick-create, list interplay) comes from the composed NoerdPage.
 */
trait NoerdDetail
{
    use NoerdPage {
        NoerdPage::getListeners as protected pageGetListeners;
    }

    public array $relationTitles = [];

    public function mount(): void
    {
        $this->initDetail();
    }

    public function store(): void
    {
        // Server-side guard: the save button is hidden for denied users, but
        // store() stays reachable via the storeDetail-{name} listener and
        // shortcut. canSaveObject() picks create (new record) or write (update).
        if (!$this->canSaveObject()) {
            return;
        }

        $this->validateFromLayout();

        $modelClass = $this->detailModel;
        $model = $modelClass::updateOrCreate(
            ['id' => $this->modelId],
            $this->writableDetailData($modelClass),
        );

        $this->finishStore($model);
    }

    /**
     * Livewire updated hook: an embedded detail mirrors its form state to the
     * hosting page (`detailDataUpdated-{name}`), e.g. for a live preview.
     * Components overriding this hook keep the sync via syncEmbeddedDetailData().
     */
    public function updatedDetailData(): void
    {
        $this->syncEmbeddedDetailData();
    }

    /**
     * Validate using rules from pageLayout YAML configuration.
     * Fields with 'required: true' will be validated as required. Declared
     * relation forms additionally contribute their validateUsing() rules —
     * applied exactly when the form carries data and would be persisted.
     */
    public function validateFromLayout(): void
    {
        $rules = [];
        $this->extractRulesFromFields($this->pageLayout['fields'] ?? [], $rules);

        $messages = [];
        if (isset($this->detailModel)) {
            foreach (RelationFormSync::forms($this->detailModel) as $key => $definition) {
                if ($definition->rules === []) {
                    continue;
                }

                if (!RelationFormSync::rendered($this->pageLayout['fields'] ?? [], $key)) {
                    continue;
                }

                $data = $this->detailData[$key] ?? null;
                if (!is_array($data) || !RelationFormSync::hasFormData($definition, $data)) {
                    continue;
                }

                foreach ($definition->rules as $field => $fieldRules) {
                    $rules['detailData.' . $key . '.' . $field] = $fieldRules;
                }

                foreach ($definition->messages as $messageKey => $message) {
                    $messages['detailData.' . $key . '.' . $messageKey] = $message;
                }
            }
        }

        if (!empty($rules)) {
            $this->validate($rules, $messages);
        }
    }

    #[On('setFieldValue')]
    public function setFieldValue(string $field, mixed $value, ?string $relationTitle = null, ?string $owner = null): void
    {
        // This is a client-dispatchable event, so it may only write into the
        // detailData form bucket — never an arbitrary component property (modelId,
        // detailModel, pageLayout, …). Every field component that emits it binds
        // into detailData.* .
        if (! str_starts_with($field, 'detailData.')) {
            return;
        }

        // The event is global; a field component that knows its owning detail
        // names it, so a stacked detail sharing the field name stays untouched.
        if ($owner !== null && $owner !== $this->getId()) {
            return;
        }

        $key = str_replace('detailData.', '', $field);
        $detailData = $this->detailData;
        data_set($detailData, $key, $value);
        $this->detailData = $detailData;

        if ($relationTitle !== null) {
            $relationKey = last(explode('.', $key));
            $this->relationTitles[$relationKey] = $relationTitle;
        }
    }

    public function clearRelation(string $fieldName): void
    {
        $key = str_replace('detailData.', '', $fieldName);
        $relationKey = last(explode('.', $key));
        $this->relationTitles[$relationKey] = '';

        if (str_contains($key, '.')) {
            $detailData = $this->detailData;
            data_set($detailData, $key, null);
            $this->detailData = $detailData;
        } elseif (array_key_exists($key, $this->detailData)) {
            $this->detailData[$key] = null;
        }
    }

    public function resolvePicklistOptions(string $picklistField): array
    {
        // $picklistField is a public method name from the field YAML, but this is
        // also a client-callable action — only invoke a genuine options provider
        // (a method declaring an `array` return type), never an action like
        // store()/delete() (which are `void`).
        if (method_exists($this, $picklistField) && $this->returnsArray($picklistField)) {
            return $this->{$picklistField}();
        }

        $provider = app(PicklistRegistry::class)->resolve($picklistField);

        return $provider ? $provider() : [];
    }

    /**
     * Livewire trait rendering hook. Components may overwrite $detailData in their own
     * mount() (e.g. `$this->detailData = $model->toArray()`), which would undo the
     * normalization done in mountDetailComponent() — so it is re-applied before every render.
     */
    public function renderingNoerdDetail(): void
    {
        // A component's custom mount() may have loaded record data after
        // initDetail() bailed out — never let it reach the Livewire snapshot.
        if ($this->objectReadBlocked) {
            $this->detailData = [];
        }

        $this->applyLayoutDefaults();
        $this->ensureCustomAttributesArray();
        $this->ensureRelationFormsHydrated();
    }

    protected function initDetail(): void
    {
        $this->initNoerdComponent(function (): void {
            // For detail components declaring $detailModel. Loads the pageLayout
            // first so the YAML quick-create opt-in below can be read from it.
            if (isset($this->detailModel)) {
                $modelClass = $this->detailModel;
                $this->mountDetailComponent(new $modelClass(), $modelClass);
            }

            $this->resolveQuickCreate();
        });
    }

    /**
     * Shared store tail for details: run the post-store chrome and report the
     * persisted record to a hosting page (`detailStored-{name}`). Standalone the
     * event simply has no listener. Custom store() overrides end with this call.
     */
    protected function finishStore(Model $model): void
    {
        $this->storeProcess($model);

        $this->dispatch('detailStored-' . $this->componentName(), modelId: $model->id);
    }

    protected function returnsArray(string $method): bool
    {
        $returnType = (new ReflectionMethod($this, $method))->getReturnType();

        return $returnType instanceof ReflectionNamedType && $returnType->getName() === 'array';
    }

    /**
     * Reduce the client-controlled $detailData to the columns the form is actually
     * allowed to persist before mass assignment. A detail YAML is a pure model
     * form, so only its declared fields may be written — this prevents a crafted
     * request from injecting extra $detailData keys (roles, prices, ownership FKs,
     * tenant_id) into a model that uses $guarded = []. Identity and tenant columns
     * are never client-assignable regardless of the layout.
     *
     * @return array<string, mixed>
     */
    protected function writableDetailData(string $modelClass): array
    {
        $data = RelationFormSync::strip($modelClass, $this->detailData);

        // The whitelist is derived from the YAML on disk, NOT from $this->pageLayout,
        // which is itself a client-writable public property.
        return $this->reduceToWritableKeys($data, $this->writableDetailDataKeys($modelClass));
    }

    /**
     * Keep only the allowed top-level keys (when any are known) and always drop
     * the identity/tenant/timestamp columns — those are set by the framework, not
     * by the client payload.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $allowed
     * @return array<string, mixed>
     */
    protected function reduceToWritableKeys(array $data, array $allowed): array
    {
        if ($allowed !== []) {
            $data = array_intersect_key($data, array_flip($allowed));
        }

        unset($data['id'], $data['tenant_id'], $data['created_at'], $data['updated_at']);

        return $data;
    }

    /**
     * Top-level $detailData keys the active layout binds (recursing into blocks),
     * read from the authoritative on-disk YAML. Empty when the component ships no
     * field layout (e.g. legacy hand-built pages) — the caller then falls back to
     * stripping only the identity/tenant columns.
     *
     * @return array<int, string>
     */
    protected function writableDetailDataKeys(string $modelClass): array
    {
        $component = $this->getDetailComponent();
        if (Str::endsWith($component, '-page')) {
            return [];
        }

        $fields = StaticConfigHelper::getComponentFields($component, $modelClass)['fields'] ?? [];

        return $this->writableKeysFromFields($fields);
    }

    /**
     * Top-level detailData keys a field layout binds (recursing into blocks).
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, string>
     */
    protected function writableKeysFromFields(array $fields): array
    {
        $keys = [];
        LayoutFields::walk($fields, function (array $field) use (&$keys): void {
            $name = $field['name'] ?? null;
            if (is_string($name) && str_starts_with($name, 'detailData.')) {
                $keys[] = Str::before(Str::after($name, 'detailData.'), '.');
            }
        });

        return array_values(array_unique($keys));
    }

    /**
     * Dispatch the embedded form-state sync to the hosting page. Kept separate so
     * components with their own updatedDetailData() hook can still trigger it.
     */
    protected function syncEmbeddedDetailData(): void
    {
        if ($this->embedded) {
            $this->dispatch('detailDataUpdated-' . $this->componentName(), detailData: $this->syncPayload());
        }
    }

    /**
     * The payload mirrored to a hosting page on form updates. Defaults to the full
     * $detailData; components override this to filter out non-scalar state
     * (relations, uploads) the page must not merge back.
     */
    protected function syncPayload(): array
    {
        return $this->detailData;
    }

    protected function mountDetailComponent(Model $model, string $modelClass): void
    {
        if (!$this->loadDetailModel($model, $modelClass)) {
            return;
        }

        // Hand-built page components (`*-page`) ship no detail YAML — they define
        // their layout in the component itself (pages use the NoerdPage trait and
        // pages/{name}.yml). Only a getDetailComponent() override pointing at a
        // `*-detail` opts back into YAML.
        $detailComponent = $this->getDetailComponent();
        if (!Str::endsWith($detailComponent, '-page')) {
            $this->pageLayout = StaticConfigHelper::getComponentFields($detailComponent, $modelClass);
        }

        $this->applyLayoutDefaults();
        $this->ensureCustomAttributesArray();
        $this->ensureRelationFormsHydrated();
    }

    /**
     * Seed $detailData with the initial values the layout declares (an explicit
     * `default:` on a field, or the first option of a YAML-listed select). Only
     * null values are filled — '' , 0 and false are answers, not gaps — so the
     * method is idempotent and never overwrites what the record or the user
     * holds. It runs from mountDetailComponent() AND renderingNoerdDetail(),
     * because components may replace $detailData wholesale in a custom mount().
     */
    protected function applyLayoutDefaults(): void
    {
        if ($this->objectReadBlocked) {
            return;
        }

        $defaults = LayoutDefaults::resolve($this->pageLayout['fields'] ?? []);
        if ($defaults === []) {
            return;
        }

        $detailData = $this->detailData;
        foreach ($defaults as $key => $value) {
            if (data_get($detailData, $key) === null) {
                data_set($detailData, $key, $value);
            }
        }

        $this->detailData = $detailData;
    }

    /**
     * Hydrate declared relation forms (DeclaresRelationForms on $detailModel) into
     * $detailData: for each form the active layout renders and whose key is not in
     * $detailData yet, fill it from the related record — or with nulls for a new
     * record, so the nested wire:model bindings never drop updates. Keyed on
     * array_key_exists, so it is idempotent and costs at most one query per
     * request; it runs from mountDetailComponent() AND renderingNoerdDetail() so
     * it survives custom mount()s that overwrite $detailData.
     */
    protected function ensureRelationFormsHydrated(): void
    {
        if (!isset($this->detailModel)) {
            return;
        }

        $modelClass = $this->detailModel;
        $forms = RelationFormSync::forms($modelClass);
        if ($forms === []) {
            return;
        }

        $owner = null;
        foreach ($forms as $key => $definition) {
            if (array_key_exists($key, $this->detailData)) {
                continue;
            }

            if (!RelationFormSync::rendered($this->pageLayout['fields'] ?? [], $key)) {
                continue;
            }

            if ($this->modelId && $owner === null) {
                $owner = $modelClass::find($this->modelId);
            }

            $this->detailData[$key] = $owner
                ? RelationFormSync::hydrate($owner, $definition)
                : RelationFormSync::emptyForm($definition);
        }
    }

    /**
     * A field bound to a nested path (e.g. `detailData.custom_attributes.sap_number`) needs its
     * parent key to exist as an array before wire:model can bind into it. A new record — or one
     * whose JSON column is still null — would otherwise bind against null and the browser-side
     * update would be lost silently.
     */
    protected function ensureCustomAttributesArray(): void
    {
        if (array_key_exists('custom_attributes', $this->detailData) && !is_array($this->detailData['custom_attributes'])) {
            $this->detailData['custom_attributes'] = [];
        }
    }

    /**
     * Extract validation rules from the field layout (recursing into blocks).
     */
    protected function extractRulesFromFields(array $fields, array &$rules): void
    {
        LayoutFields::walk($fields, function (array $field) use (&$rules): void {
            if (isset($field['name']) && ($field['required'] ?? false)) {
                $rules[$field['name']] = ['required'];
            }
        });
    }

    /**
     * Get the event listeners for the component: the NoerdPage set plus the store
     * trigger a hosting page dispatches (`storeDetail-{name}`).
     */
    protected function getListeners(): array
    {
        return $this->pageGetListeners() + [
            'storeDetail-' . $this->componentName() => 'store',
        ];
    }
}
