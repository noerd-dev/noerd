@php
    // showIf/showIfNot come from the field YAML. Keep the wire path to a bare
    // property expression and escape the compared value for both the JS single-
    // quoted string and the surrounding HTML attribute, so a stray quote can
    // never break the x-show expression out of its context.
    $sanitizeWirePath = fn ($path): string => preg_replace('/[^A-Za-z0-9_.]/', '', (string) $path);
    $escapeAlpineValue = fn ($value): string => htmlspecialchars(addslashes((string) $value), ENT_QUOTES);

    $getShowIfDirective = function($field) use ($sanitizeWirePath, $escapeAlpineValue): string {
        $directive = '';

        // showIf - positive condition
        if (isset($field['showIf'])) {
            if (is_string($field['showIf'])) {
                $directive = 'x-show="$wire.' . $sanitizeWirePath($field['showIf']) . '"';
            } elseif (is_array($field['showIf'])) {
                $directive = 'x-show="$wire.' . $sanitizeWirePath($field['showIf']['field'])
                    . " === '" . $escapeAlpineValue($field['showIf']['value']) . "'\"";
            }
        }

        // showIfNot - negated condition
        if (isset($field['showIfNot'])) {
            if (is_string($field['showIfNot'])) {
                $directive = 'x-show="!$wire.' . $sanitizeWirePath($field['showIfNot']) . '"';
            } elseif (is_array($field['showIfNot'])) {
                $directive = 'x-show="$wire.' . $sanitizeWirePath($field['showIfNot']['field'])
                    . " !== '" . $escapeAlpineValue($field['showIfNot']['value']) . "'\"";
            }
        }

        return $directive;
    };

    $fieldTypeRegistry = app(\Noerd\Services\FieldTypeRegistry::class);
    $themeRegistry = app(\Noerd\Services\ThemeRegistry::class);

    $theme = \Noerd\Helpers\ThemeHelper::fromLayout(['theme' => $theme ?? null]);
    $themeDefinition = $themeRegistry->get($theme);
    // Save/restore the theme context around this block (mirrors NoerdPage's
    // rendering hooks): two sibling blocks with different `theme:` keys must
    // not leak the first block's theme into chrome rendered after it.
    $themeContextBefore = \Noerd\Support\ThemeContext::current();
    \Noerd\Support\ThemeContext::set($theme);
    $numberedRowIndex = 0;

    // Object save permission: when the hosting component denies saving the
    // form's current state (create for a new record, write for an update — see
    // canSaveObject()), every field renders readonly/disabled. Client-side only
    // a UX affordance — the hard boundary stays the store()/delete() guards in
    // the traits. Nested `type: block` includes re-enter this template and
    // recompute the flag. Bespoke components exposing only canWriteObject()
    // keep the plain write check.
    $blockWriteDenied = isset($this)
        && (method_exists($this, 'canSaveObject')
            ? ! $this->canSaveObject()
            : (method_exists($this, 'canWriteObject') && ! $this->canWriteObject()));
@endphp
{{-- not-last:mb-8 separates the block from custom content rendered after it
     (e.g. a tab slot); when the block is the last element the page chrome's
     bottom padding provides the gap instead. --}}
<div class="not-last:mb-8">
    @if(isset($title) || isset($description))
        @include('noerd::components.detail.block-head', ['title' => __($title ?? ''), 'description' => __($description ?? '')])
    @endif
    <div @if($theme !== 'default') data-theme="{{ $theme }}" @endif class="grid {{ $themeDefinition->gridClasses }} grid-cols-1 sm:grid-cols-{{$cols ?? '12'}}">
        @foreach($fields ?? [] as $field)
            @if(isset($field['show']) && !$field['show'])
            @elseif(isset($field['viewExists']) && !\Illuminate\Support\Facades\View::exists($field['viewExists']))
            @elseif($field['type'] === 'block')
                {{-- Nested block with its own title and fields --}}
                <div class="{{ $themeDefinition->fullWidthRows ? 'col-span-full' : 'col-span-1 sm:col-span-' . ($field['colspan'] ?? '12') }}" {!! $getShowIfDirective($field) !!}>
                    @include('noerd::components.detail.block', [
                        'title' => $field['title'] ?? null,
                        'description' => $field['description'] ?? null,
                        'fields' => $field['fields'] ?? [],
                        'cols' => $field['cols'] ?? $cols ?? '12',
                        'modelId' => $modelId ?? null,
                        'theme' => $field['theme'] ?? $theme,
                    ])
                </div>
            @else
                @php
                    $fieldThemeDefinition = $themeRegistry->get($field['theme'] ?? $theme);
                    $fieldTheme = $fieldThemeDefinition->name;
                    $field['theme'] = $fieldTheme;
                    if ($fieldThemeDefinition->numbersRows && ($field['type'] ?? '') !== 'spacer') {
                        $numberedRowIndex++;
                        $field['number'] ??= $numberedRowIndex;
                    }

                    if ($blockWriteDenied) {
                        $field['readonly'] = true;
                    }

                    // Set unconditionally so optional keys (helpText) never leak into
                    // the next field; cleared again after the loop.
                    \Noerd\Support\FieldContext::set($field);
                @endphp
                <div class="{{ $fieldThemeDefinition->fullWidthRows ? 'col-span-full' : 'col-span-1 sm:col-span-' . ($field['colspan'] ?? '3') }}" {!! $getShowIfDirective($field) !!}>
                    @php
                        $fieldTypeDefinition = $fieldTypeRegistry->resolve($field['type'] ?? '');
                        $resolvedRendererProps = $fieldTypeDefinition?->resolveProps(
                            $field,
                            $this ?? null,
                            $detailData ?? null,
                            $modelId ?? null,
                        ) ?? [];
                        $resolvedRendererKey = $fieldTypeDefinition?->resolveKey(
                            $field,
                            $this ?? null,
                            $detailData ?? null,
                            $modelId ?? null,
                        );

                        // Element templates live in theme folders; a theme that does not
                        // ship an element falls back to the default theme's template and
                        // finally to the renderer registered on the field type. Livewire
                        // renderers theme via the "-{theme}" suffix convention instead.
                        $rendererTarget = null;
                        if ($fieldTypeDefinition) {
                            $rendererTarget = $fieldTypeDefinition->kind === 'livewire'
                                ? \Noerd\Support\ThemeElementResolver::resolveLivewire($fieldTypeDefinition, $fieldTheme)
                                : \Noerd\Support\ThemeElementResolver::resolveInclude($fieldTypeDefinition, $fieldTheme);
                        }
                    @endphp

                    @if($fieldTypeDefinition?->kind === 'livewire')
                        @livewire(
                            $rendererTarget,
                            $resolvedRendererProps,
                            key($resolvedRendererKey ?? ($field['name'] ?? $field['type']) . '-' . ($modelId ?? 'new'))
                        )
                    @elseif($fieldTypeDefinition?->kind === 'include')
                        @php
                            // An element the theme does not ship renders the default
                            // template inside the theme's row chrome, so the form keeps
                            // its rhythm (row number, label position) for every type.
                            $usesThemeElement = str_starts_with((string) $rendererTarget, "themes::{$fieldTheme}.")
                                || in_array($field['type'] ?? '', ['spacer', 'checkbox'], true);
                            $unlabelledProps = array_merge($resolvedRendererProps, [
                                'field' => array_merge($resolvedRendererProps['field'] ?? $field, ['label' => '', 'helpText' => null]),
                            ]);
                        @endphp
                        @if (! $usesThemeElement && $fieldThemeDefinition->numbersRows)
                            <x-noerd::detail.numbered-row :field="$field" labelTop :showError="false">
                                @include($rendererTarget, $unlabelledProps)
                            </x-noerd::detail.numbered-row>
                        @elseif (! $usesThemeElement && $fieldTheme === 'compact')
                            <div class="flex items-start gap-2">
                                <x-noerd::input-label
                                    for="{{ $field['name'] ?? '' }}"
                                    :value="__($field['label'] ?? '')"
                                    :required="$field['required'] ?? false"
                                    :title="__($field['label'] ?? '')"
                                    class="w-36 shrink-0 truncate pt-1 pb-0!"
                                />
                                <div class="min-w-0 flex-1">
                                    @include($rendererTarget, $unlabelledProps)
                                </div>
                            </div>
                        @else
                            @include($rendererTarget, $resolvedRendererProps)
                        @endif
                    @else
                        @php
                            $fieldType = $field['type'] ?? '';
                            $looksLikeRelation = $fieldType === 'relation' || \Illuminate\Support\Str::endsWith($fieldType, 'Relation');
                        @endphp

                        @if($looksLikeRelation)
                            @php
                                throw new \RuntimeException("Relation field type [{$fieldType}] is not registered. Register it in a module service provider and reference that explicit type in YAML.");
                            @endphp
                        @else
                            @include(\Noerd\Support\ThemeElementResolver::resolveFallbackInput($fieldTheme), ['field' => $field])
                        @endif
                    @endif
                </div>
            @endif
        @endforeach
        @php
            \Noerd\Support\FieldContext::clear();
            \Noerd\Support\ThemeContext::set($themeContextBefore);
        @endphp
    </div>
</div>
