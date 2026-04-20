<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

uses(Tests\TestCase::class);

/**
 * Components that define their own custom data properties
 * instead of using the NoerdDetail trait's $detailData.
 * These are excluded from the detailData.* prefix check.
 */
$customPropertyComponents = [];

it('ensures all YAML detail files use detailData prefix for NoerdDetail components', function () use ($customPropertyComponents): void {
    $directories = [
        base_path('app-configs'),
        ...glob(base_path('app-modules/*/app-configs'), GLOB_ONLYDIR),
    ];

    $violations = [];

    foreach ($directories as $directory) {
        $yamlFiles = File::glob($directory . '/**/details/*-detail.yml');

        foreach ($yamlFiles as $yamlFile) {
            $componentName = basename($yamlFile, '.yml');

            if (in_array($componentName, $customPropertyComponents)) {
                continue;
            }

            $content = Yaml::parseFile($yamlFile);
            $fields = $content['fields'] ?? [];

            foreach ($fields as $field) {
                $name = $field['name'] ?? null;
                if (! $name) {
                    continue;
                }

                // Skip fields that don't have a dot (e.g., "logo", "staff", "substitutes")
                if (! str_contains($name, '.')) {
                    continue;
                }

                // Skip relationTitles fields
                if (str_starts_with($name, 'relationTitles.')) {
                    continue;
                }

                // The field name must start with detailData.
                if (! str_starts_with($name, 'detailData.')) {
                    $relativePath = str_replace(base_path('/'), '', $yamlFile);
                    $violations[] = "{$relativePath}: field '{$name}' should use 'detailData.' prefix";
                }
            }
        }
    }

    expect($violations)->toBeEmpty(
        "Found YAML detail fields with incorrect prefixes:\n" . implode("\n", $violations),
    );
});
