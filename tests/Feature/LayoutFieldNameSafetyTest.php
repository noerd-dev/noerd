<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Models\NoerdUser;
use Noerd\Support\LayoutFields;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Field names reach wire:model, wire:click (type: button) and Alpine
 | expressions of the themes. A resolved layout — YAML plus a layout override a
 | tenant stored — never carries a name that could end a string or form an
 | expression, and a button calls nothing but a plain method name.
 */

const ZZ_UNSAFE_FIELD_NAME = "foo();alert(document.cookie)//";

it('keeps the names real column paths use and drops every other one, also inside blocks', function (): void {
    $dropped = [];

    $fields = LayoutFields::withoutUnsafeNames([
        ['name' => 'detailData.name'],
        ['name' => 'detailData.Kante A oben'],
        ['name' => 'detailData.Nest N/S'],
        ['name' => 'detailData.Länge'],
        ['name' => 'detailData.custom_attributes.sap_number'],
        ['name' => ZZ_UNSAFE_FIELD_NAME, 'type' => 'button'],
        ['name' => "detailData.x' + alert(1) + '"],
        ['type' => 'spacer'],
        ['type' => 'block', 'fields' => [
            ['name' => 'detailData.email'],
            ['name' => 'detailData.a`b'],
        ]],
    ], function (string $name) use (&$dropped): void {
        $dropped[] = $name;
    });

    expect(array_column($fields, 'name'))->toBe([
        'detailData.name',
        'detailData.Kante A oben',
        'detailData.Nest N/S',
        'detailData.Länge',
        'detailData.custom_attributes.sap_number',
    ])
        ->and($fields[5])->toBe(['type' => 'spacer'])
        ->and(array_column($fields[6]['fields'], 'name'))->toBe(['detailData.email'])
        ->and($dropped)->toBe([ZZ_UNSAFE_FIELD_NAME, "detailData.x' + alert(1) + '", 'detailData.a`b']);
});

it('drops a field with an unsafe name that a layout override adds to a resolved detail', function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    app()->instance(StaticConfigHelper::LAYOUT_OVERRIDES_BINDING, new class {
        public function apply(string $viewType, string $component, array $config, ?string $modelClass = null): array
        {
            $config['fields'][] = ['name' => ZZ_UNSAFE_FIELD_NAME, 'label' => 'Injected', 'type' => 'button'];
            $config['fields'][] = ['name' => 'detailData.zz_safe', 'label' => 'Safe', 'type' => 'text'];

            return $config;
        }
    });
    StaticConfigHelper::flushRuntimeCaches();

    $names = [];
    LayoutFields::walk(StaticConfigHelper::getComponentFields('noerd-user-detail')['fields'], function (array $field) use (&$names): void {
        $names[] = $field['name'] ?? null;
    });

    expect($names)->toContain('detailData.zz_safe')
        ->not->toContain(ZZ_UNSAFE_FIELD_NAME);
});

it('lets a button field call only a plain method name in every theme', function (string $theme): void {
    view()->share('errors', new ViewErrorBag());

    $html = view("themes::{$theme}.button", [
        'field' => ['name' => ZZ_UNSAFE_FIELD_NAME, 'label' => 'Go', 'type' => 'button'],
    ])->render();

    expect($html)->toContain('wire:click="fooalertdocumentcookie"')
        ->not->toMatch('/wire:click="[^"]*\(/');
})->with(['default', 'compact', 'numbered']);
