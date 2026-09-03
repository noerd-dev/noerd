<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Noerd\Services\ThemeRegistry;

/**
 * Resolves the blade template that renders a form element under the active
 * theme. Element templates live in theme folders addressed through the
 * `themes::` view namespace (project roots first, noerd built-ins last);
 * a theme that does not ship an element falls back to the default theme's
 * template, and finally to the renderer registered on the field type.
 */
final class ThemeElementResolver
{
    /**
     * Template for an include-kind field type. The element name is the
     * basename of the registered target (`noerd::components.forms.input` →
     * `input`), so a theme can skin field types from any module.
     */
    public static function resolveInclude(FieldTypeDefinition $definition, string $theme): string
    {
        $element = Str::afterLast($definition->target, '.');

        return self::resolveElement($element, $theme) ?? $definition->target;
    }

    /**
     * Component name for a livewire-kind field type: a namespace-aware
     * `{name}-{theme}` sibling wins when it exists. Themes cannot hold
     * livewire components, so third-party livewire field types theme
     * through this suffix convention.
     */
    public static function resolveLivewire(FieldTypeDefinition $definition, string $theme): string
    {
        if ($theme === 'default') {
            return $definition->target;
        }

        $namespace = 'noerd';
        $componentName = $definition->target;
        if (str_contains($definition->target, '::')) {
            [$namespace, $componentName] = explode('::', $definition->target, 2);
        }

        $variantName = $componentName . '-' . $theme;
        if (View::exists($namespace . '::components.' . $variantName)) {
            return str_contains($definition->target, '::')
                ? $namespace . '::' . $variantName
                : $variantName;
        }

        return $definition->target;
    }

    /**
     * The generic input template used for unregistered field types.
     */
    public static function resolveFallbackInput(string $theme): string
    {
        return self::resolveElement('input', $theme) ?? 'themes::default.input';
    }

    /**
     * Template included by the relation-field livewire components
     * (elements `relation-field` / `polymorphic-relation-field`).
     */
    public static function resolveRelationTemplate(string $element, string $theme): string
    {
        return self::resolveElement($element, $theme) ?? 'themes::default.' . $element;
    }

    /**
     * Walk the theme fallback chain for a bare element name.
     */
    public static function resolveElement(string $element, string $theme): ?string
    {
        // Ensure roots are discovered and the themes:: namespace is synced.
        app(ThemeRegistry::class)->get($theme);

        foreach (array_unique([$theme, 'default']) as $candidateTheme) {
            $candidate = "themes::{$candidateTheme}.{$element}";
            if (View::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
