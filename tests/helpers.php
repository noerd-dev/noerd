<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Assert;

if (!function_exists('requiredLayoutFields')) {
    /**
     * Field names marked required in the component's pageLayout.
     *
     * Returns the layout keys (incl. the "detailData." prefix) so they line up
     * with the validation error keys asserted by assertHasErrors(). Mirrors
     * NoerdDetail::extractRulesFromFields() (recurse into type: block, collect
     * required: true) so validation tests assert against whatever the YAML
     * currently declares required instead of hard-coding a field name.
     *
     * @return array<int, string>
     */
    function requiredLayoutFields($component): array
    {
        $layout = $component->get('pageLayout') ?? [];

        return extractRequiredLayoutFields($layout['fields'] ?? []);
    }
}

if (!function_exists('extractRequiredLayoutFields')) {
    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, string>
     */
    function extractRequiredLayoutFields(array $fields): array
    {
        $required = [];

        foreach ($fields as $field) {
            if (($field['type'] ?? null) === 'block') {
                $required = array_merge(
                    $required,
                    extractRequiredLayoutFields($field['fields'] ?? []),
                );

                continue;
            }

            if (($field['required'] ?? false) && isset($field['name'])) {
                $required[] = $field['name'];
            }
        }

        return $required;
    }
}

if (!function_exists('validDetailPayload')) {
    /**
     * Valid detailData array sourced from the model factory, merged with overrides.
     *
     * Keys are un-prefixed field names (tenant_id, name, zipcode, …), exactly as
     * expected by ->set('detailData', …). make($overrides) avoids spinning up
     * relation factories for overridden foreign keys, and id/timestamps are
     * stripped so no existing record is implied.
     *
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function validDetailPayload(string $modelClass, array $overrides = []): array
    {
        $attributes = $modelClass::factory()->make($overrides)->toArray();

        return array_merge(
            Arr::except($attributes, ['id', 'created_at', 'updated_at', 'deleted_at']),
            $overrides,
        );
    }
}

if (!function_exists('registerTestLivewireRoute')) {
    /**
     * Register a named Route::livewire() route from inside a test.
     *
     * RouteServiceProvider refreshes the collection's name lookups once on boot,
     * so a route added afterwards is invisible to Route::has()/getByName() until
     * the lookups are refreshed again.
     */
    function registerTestLivewireRoute(string $uri, string $component, string $name): void
    {
        Route::livewire($uri, $component)->name($name);
        Route::getRoutes()->refreshNameLookups();
    }
}

if (!function_exists('assertElementHasClasses')) {
    /**
     * Assert that ONE element in the markup carries all given CSS classes.
     *
     * Matching a multi-class string as one substring (e.g. assertSeeHtml('h-7 px-2'))
     * breaks the moment a Tailwind class sorter reorders the attribute, even though
     * the markup is unchanged. This checks each class as a whole token within a
     * single class attribute, so order and neighbouring classes are irrelevant while
     * the "same element" guarantee is kept.
     *
     * @param  array<int, string>  $classes
     */
    function assertElementHasClasses(string $html, array $classes, string $message = ''): void
    {
        preg_match_all('/class="([^"]*)"/', $html, $matches);

        foreach ($matches[1] as $classAttribute) {
            $present = preg_split('/\s+/', mb_trim($classAttribute)) ?: [];

            if (array_diff($classes, $present) === []) {
                Assert::assertTrue(true);

                return;
            }
        }

        Assert::fail(
            $message !== ''
                ? $message
                : 'No element carries all of these classes: ' . implode(' ', $classes),
        );
    }
}

if (!function_exists('assertNoElementHasClasses')) {
    /**
     * The negative counterpart of assertElementHasClasses().
     *
     * @param  array<int, string>  $classes
     */
    function assertNoElementHasClasses(string $html, array $classes, string $message = ''): void
    {
        preg_match_all('/class="([^"]*)"/', $html, $matches);

        foreach ($matches[1] as $classAttribute) {
            $present = preg_split('/\s+/', mb_trim($classAttribute)) ?: [];

            if (array_diff($classes, $present) === []) {
                Assert::fail(
                    $message !== ''
                        ? $message
                        : 'An element unexpectedly carries all of these classes: ' . implode(' ', $classes),
                );
            }
        }

        Assert::assertTrue(true);
    }
}
