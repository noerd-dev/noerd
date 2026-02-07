<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

uses(Tests\TestCase::class);

it('ensures all YAML detail tab arguments only reference $modelId, not component-specific property names', function (): void {
    $directories = [
        base_path('app-configs'),
        ...glob(base_path('app-modules/*/app-configs'), GLOB_ONLYDIR),
        ...glob(base_path('app-modules/*/app-contents'), GLOB_ONLYDIR),
    ];

    $violations = [];

    foreach ($directories as $directory) {
        $yamlFiles = File::glob($directory . '/**/details/*-detail.yml');

        foreach ($yamlFiles as $yamlFile) {
            $content = Yaml::parseFile($yamlFile);
            $tabs = $content['tabs'] ?? [];

            foreach ($tabs as $tab) {
                $arguments = $tab['arguments'] ?? [];

                foreach ($arguments as $key => $value) {
                    if (! is_string($value) || ! str_starts_with($value, '$')) {
                        continue;
                    }

                    $varName = substr($value, 1);

                    // $modelId is the only variable reliably available in tabs.blade.php
                    if ($varName !== 'modelId') {
                        $relativePath = str_replace(base_path('/'), '', $yamlFile);
                        $tabLabel = $tab['label'] ?? $tab['component'] ?? 'unknown';
                        $violations[] = "{$relativePath}: tab '{$tabLabel}' argument '{$key}' references '\${$varName}' — should use '\$modelId' instead";
                    }
                }
            }
        }
    }

    expect($violations)->toBeEmpty(
        "Found YAML tab arguments referencing properties other than \$modelId:\n" . implode("\n", $violations)
    );
});
