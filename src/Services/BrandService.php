<?php

namespace Noerd\Services;

class BrandService
{
    /**
     * Get the resolved color values for the active brand,
     * with individual overrides applied.
     *
     * @return array<string, string>
     */
    public function colors(): array
    {
        $brand = config('noerd.brand.active', 'default');
        $presets = config("noerd.brand.presets.{$brand}", config('noerd.brand.presets.default', []));
        $overrides = config('noerd.brand.overrides', []);

        $resolved = [];
        foreach ($presets as $key => $value) {
            $override = $overrides[$key] ?? null;
            $resolved[$key] = $override ?: $value;
        }

        return $resolved;
    }

    /**
     * Get a single resolved color value.
     */
    public function color(string $key): ?string
    {
        return $this->colors()[$key] ?? null;
    }

    /**
     * Get CSS custom property overrides as an inline style string.
     */
    public function cssCustomProperties(): string
    {
        $colors = $this->colors();
        $parts = [];
        foreach ($colors as $key => $value) {
            $parts[] = "--color-{$key}: {$value};";
        }

        return implode(' ', $parts);
    }
}
