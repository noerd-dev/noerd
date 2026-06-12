<?php

use Illuminate\Support\Arr;

if (! function_exists('requiredLayoutFields')) {
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

if (! function_exists('extractRequiredLayoutFields')) {
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

if (! function_exists('validDetailPayload')) {
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
