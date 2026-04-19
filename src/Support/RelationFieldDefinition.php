<?php

namespace Noerd\Support;

use Illuminate\Support\Str;

class RelationFieldDefinition
{
    /**
     * @param  callable|null  $titleResolver
     */
    public function __construct(
        public string $listComponent,
        public ?string $detailComponent = null,
        public ?string $modelClass = null,
        public $titleResolver = null,
        public ?string $selectEvent = null,
    ) {}

    /**
     * @param  callable|string|null  $titleResolver
     */
    public static function model(
        string $listComponent,
        ?string $detailComponent,
        ?string $modelClass,
        callable|string|null $titleResolver = 'name',
        ?string $selectEvent = null,
    ): self {
        return new self(
            listComponent: $listComponent,
            detailComponent: $detailComponent,
            modelClass: $modelClass,
            titleResolver: is_string($titleResolver)
                ? static fn(mixed $model): mixed => data_get($model, $titleResolver)
                : $titleResolver,
            selectEvent: $selectEvent,
        );
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
            $selectedLanguage = 'de';
            if (function_exists('app') && app()->bound('session.store')) {
                $selectedLanguage = session('selectedLanguage', 'de');
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
}
