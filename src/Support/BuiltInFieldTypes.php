<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Str;
use Noerd\Services\FieldTypeRegistry;

/**
 * The detail field types the package ships, registered on the FieldTypeRegistry
 * by the service provider. Most are a plain Blade partial receiving the field
 * array — those come from ONE map; only the three types needing extra props
 * keep a resolver of their own. A type whose template exists in the default
 * theme only is registered on the theme template itself (ThemeElementResolver
 * walks the theme chain from the basename); the `noerd::components.forms.*`
 * partials remain for the elements a view also includes directly.
 *
 * @internal
 */
final class BuiltInFieldTypes
{
    /** @var array<string, string> YAML type => Blade partial */
    private const VIEW_ONLY_TYPES = [
        'picklist' => 'noerd::components.forms.picklist',
        'setupCollectionSelect' => 'noerd::components.forms.setup-collection-select',
        'belongsToMany' => 'themes::default.belongs-to-many',
        'checkbox' => 'noerd::components.forms.checkbox',
        'richText' => 'themes::default.rich-text',
        'translatableRichText' => 'themes::default.translatable-rich-text',
        'translatableText' => 'noerd::components.forms.translatable-text',
        'translatableTextarea' => 'noerd::components.forms.translatable-textarea',
        'button' => 'themes::default.button',
        'colorHex' => 'themes::default.color-hex',
        'currency' => 'noerd::components.forms.input-currency',
        'textarea' => 'noerd::components.forms.input-textarea',
        'file' => 'noerd::components.forms.file',
        'spacer' => 'noerd::components.forms.spacer',
        'phone' => 'noerd::components.forms.phone',
        'email' => 'noerd::components.forms.email',
    ];

    public static function register(FieldTypeRegistry $registry): void
    {
        foreach (self::VIEW_ONLY_TYPES as $type => $view) {
            $registry->register($type, FieldTypeDefinition::include(
                $view,
                resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
            ));
        }

        // A select may build its options from a component method at render time.
        $registry->register('select', FieldTypeDefinition::include(
            'noerd::components.forms.input-select',
            resolver: function (array $field, mixed $component, mixed $detailData, mixed $modelId): array {
                $optionsMethod = $field['optionsMethod'] ?? null;

                if ($optionsMethod && $component && method_exists($component, $optionsMethod)) {
                    $options = [];

                    foreach ($component->{$optionsMethod}() as $value => $label) {
                        $options[] = ['value' => $value, 'label' => $label];
                    }

                    $field['options'] = $options;
                }

                return ['field' => $field];
            },
        ));

        $registry->register('image', FieldTypeDefinition::include(
            'themes::default.image',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => [
                'field' => $field,
                'detailData' => $detailData,
            ],
        ));

        $registry->register('icon', FieldTypeDefinition::include(
            'themes::default.icon',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => [
                'field' => $field,
                'iconValue' => data_get($component->detailData ?? $detailData ?? [], Str::after($field['name'] ?? '', 'detailData.')),
            ],
        ));
    }
}
