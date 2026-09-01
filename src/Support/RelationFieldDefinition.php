<?php

namespace Noerd\Support;

use Illuminate\Container\Container;
use Illuminate\Support\Str;

class RelationFieldDefinition
{
    /**
     * @param  ?string  $detailRoute  Named Route::livewire() route of the detail full
     *                                page (e.g. 'crm.account.detail'); when set, the
     *                                relation detail opens via Noerd::modalRoute()
     *                                (URL rewrite) and $detailComponent is not used
     * @param  callable|null  $titleResolver
     * @param  ?string  $fieldComponent  Livewire component rendering the field in the
     *                                   detail form; null uses the generic
     *                                   'noerd-relation-field'. A custom component must
     *                                   extend Noerd\Livewire\RelationFieldComponent
     */
    public function __construct(
        public string $listComponent,
        public ?string $detailComponent = null,
        public ?string $detailRoute = null,
        public ?string $modelClass = null,
        public mixed $titleResolver = null,
        public ?string $selectEvent = null,
        public ?string $fieldComponent = null,
    ) {}

    /**
     * @param  callable|string|null  $titleResolver
     */
    public static function model(
        string $listComponent,
        ?string $detailComponent = null,
        ?string $modelClass = null,
        callable|string|null $titleResolver = 'name',
        ?string $selectEvent = null,
        ?string $detailRoute = null,
        ?string $fieldComponent = null,
    ): self {
        return new self(
            listComponent: $listComponent,
            detailComponent: $detailComponent,
            detailRoute: $detailRoute,
            modelClass: $modelClass,
            titleResolver: is_string($titleResolver)
                ? static fn(mixed $model): mixed => data_get($model, $titleResolver)
                : $titleResolver,
            selectEvent: $selectEvent,
            fieldComponent: $fieldComponent,
        );
    }

    public static function normalizeDisplayValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return self::normalizeDisplayValue($decoded);
            }

            return $value;
        }

        if (is_array($value)) {
            // Container-safe: this static helper also runs in pure unit tests
            // without a booted application, where neither session nor config
            // are bound — fall back to 'en' there.
            $app = Container::getInstance();
            if ($app->bound('session.store')) {
                $selectedLanguage = session('selectedLanguage') ?? app()->getLocale();
            } elseif ($app->bound('config')) {
                $selectedLanguage = app()->getLocale();
            } else {
                $selectedLanguage = 'en';
            }

            if (isset($value[$selectedLanguage]) && is_scalar($value[$selectedLanguage])) {
                return (string) $value[$selectedLanguage];
            }

            foreach ($value as $item) {
                if (is_scalar($item) && $item !== '') {
                    return (string) $item;
                }
            }

            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    public function resolveTitleForValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! $this->modelClass || ! class_exists($this->modelClass)) {
            return '';
        }

        $model = $this->modelClass::query()->find($value);
        if (! $model) {
            return '';
        }

        return $this->resolveTitle($model);
    }

    public function resolveTitle(mixed $model): string
    {
        if ($this->titleResolver) {
            return self::normalizeDisplayValue(($this->titleResolver)($model));
        }

        return self::normalizeDisplayValue(data_get($model, 'name'));
    }

    public function getDetailComponent(): ?string
    {
        if ($this->detailComponent) {
            return $this->detailComponent;
        }

        if (! Str::endsWith($this->listComponent, '-list')) {
            return null;
        }

        return Str::singular(Str::before($this->listComponent, '-list')) . '-detail';
    }

    public function getSelectEvent(): ?string
    {
        if ($this->selectEvent) {
            return $this->selectEvent;
        }

        if (! Str::endsWith($this->listComponent, '-list')) {
            return null;
        }

        $listWithoutNamespace = Str::afterLast($this->listComponent, '::');
        $entity = Str::singular(Str::before($listWithoutNamespace, '-list'));

        return Str::camel($entity) . 'Selected';
    }
}
