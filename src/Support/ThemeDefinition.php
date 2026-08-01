<?php

namespace Noerd\Support;

use Symfony\Component\Yaml\Yaml;

/**
 * Describes one form theme (default, compact, numbered, …).
 *
 * A theme is a self-contained folder of element blade templates plus a
 * theme.yml holding these values. Besides the YAML-driven form grid, a theme
 * also styles the hand-written position tables of documents (order, quote,
 * invoice, …) through the `x-noerd::positions.*` and `x-noerd::forms.control`
 * components — a theme therefore gets position styling for free.
 */
final class ThemeDefinition
{
    public function __construct(
        public string $name,
        public ?string $label = null,
        public string $gridClasses = 'py-8 pt-4 gap-6',
        public bool $fullWidthRows = false,
        public bool $numbersRows = false,
        public string $spacerClass = 'h-16',
        public string $controlClasses = 'w-full border rounded-lg block appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 placeholder-zinc-400 shadow-xs border-zinc-200 border-b-zinc-300/80 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2 disabled:text-zinc-500 disabled:bg-zinc-50 disabled:shadow-none',
        public string $tableClasses = 'table table-sm w-full',
        public string $headCellClasses = 'pr-3 pb-1',
        public string $cellClasses = 'pr-3 pt-2 align-middle',
        public string $rowClasses = 'w-full',
        public string $sectionPadding = 'py-8',
        public string $totalsPadding = 'pt-6',
        public string $controlSize = 'md',
        public ?string $buttonClasses = null,
    ) {}

    /**
     * Hydrate a definition from a theme.yml file. Unknown keys are ignored and
     * every key is optional, so a copied theme folder only has to override
     * what it changes.
     */
    public static function fromYamlFile(string $name, string $path): self
    {
        $data = Yaml::parseFile($path);

        return self::fromArray($name, is_array($data) ? $data : []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $name, array $data): self
    {
        $defaults = new self($name);

        $string = fn(string $key): string => is_string($data[$key] ?? null) && $data[$key] !== ''
            ? $data[$key]
            : $defaults->{$key};

        return new self(
            name: $name,
            label: is_string($data['label'] ?? null) ? $data['label'] : null,
            gridClasses: $string('gridClasses'),
            fullWidthRows: (bool) ($data['fullWidthRows'] ?? false),
            numbersRows: (bool) ($data['numbersRows'] ?? false),
            spacerClass: $string('spacerClass'),
            controlClasses: $string('controlClasses'),
            tableClasses: $string('tableClasses'),
            headCellClasses: $string('headCellClasses'),
            cellClasses: $string('cellClasses'),
            rowClasses: $string('rowClasses'),
            sectionPadding: $string('sectionPadding'),
            totalsPadding: $string('totalsPadding'),
            controlSize: $string('controlSize'),
            buttonClasses: is_string($data['buttonClasses'] ?? null) && $data['buttonClasses'] !== ''
                ? $data['buttonClasses']
                : null,
        );
    }
}
