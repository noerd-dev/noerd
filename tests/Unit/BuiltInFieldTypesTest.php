<?php

declare(strict_types=1);

use Noerd\Services\FieldTypeRegistry;
use Noerd\Support\BuiltInFieldTypes;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('registers every shipped field type on a registry', function (): void {
    $registry = new FieldTypeRegistry();

    BuiltInFieldTypes::register($registry);

    $shipped = [
        'picklist', 'setupCollectionSelect', 'belongsToMany', 'checkbox', 'richText', 'translatableRichText',
        'translatableText', 'translatableTextarea', 'button', 'colorHex', 'currency', 'textarea', 'file',
        'spacer', 'phone', 'email', 'select', 'image', 'icon',
    ];

    foreach ($shipped as $type) {
        expect($registry->has($type))->toBeTrue("field type {$type} is not registered");
    }

    expect($registry->get('checkbox')?->resolveProps(['name' => 'detailData.flag', 'type' => 'checkbox']))
        ->toBe(['field' => ['name' => 'detailData.flag', 'type' => 'checkbox']]);
});

it('lets a select build its options from a component method at render time', function (): void {
    $registry = new FieldTypeRegistry();
    BuiltInFieldTypes::register($registry);

    $component = new class {
        /** @return array<string, string> */
        public function zzStatusOptions(): array
        {
            return ['open' => 'Open', 'closed' => 'Closed'];
        }
    };

    $props = $registry->get('select')?->resolveProps(
        ['name' => 'detailData.status', 'type' => 'select', 'optionsMethod' => 'zzStatusOptions'],
        $component,
    );

    expect($props['field']['options'] ?? null)->toBe([
        ['value' => 'open', 'label' => 'Open'],
        ['value' => 'closed', 'label' => 'Closed'],
    ]);

    // Without the method the YAML options stay untouched.
    $props = $registry->get('select')?->resolveProps(
        ['name' => 'detailData.status', 'type' => 'select', 'optionsMethod' => 'missing', 'options' => [['value' => 'a', 'label' => 'A']]],
        $component,
    );

    expect($props['field']['options'] ?? null)->toBe([['value' => 'a', 'label' => 'A']]);
});

it('hands the icon field its current value from the component', function (): void {
    $registry = new FieldTypeRegistry();
    BuiltInFieldTypes::register($registry);

    $component = new class {
        /** @var array<string, mixed> */
        public array $detailData = ['heroicon' => 'cube'];
    };

    $props = $registry->get('icon')?->resolveProps(['name' => 'detailData.heroicon', 'type' => 'icon'], $component);

    expect($props['iconValue'] ?? null)->toBe('cube');
});
