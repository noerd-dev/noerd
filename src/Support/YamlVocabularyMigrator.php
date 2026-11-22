<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Rewrites app-config YAML written in an earlier vocabulary to the current
 * one. It works line by line on the source text — comments, order and
 * formatting of a file stay as they are — and is idempotent: a file in the
 * current vocabulary is not touched.
 *
 * It understands block style AND flow style (`- {field: a, type: colored_text}`),
 * quoted keys (`"type": …`), a space before the colon (`type : …`), a UTF-8 BOM,
 * CRLF line endings and tabs after the colon. The content of a block scalar
 * (`description: |`) is text, not configuration, and is never rewritten.
 *
 * Modal targets have ONE vocabulary (see YamlModalTarget): a modal is opened
 * by `modalRoute:` / `modalComponent:` (prefixed `newModal…` for the "+" of a
 * navigation entry, `showMoreModalRoute:` for a page widget), `route:` only
 * navigates. Which `route:` / `component:` meant a modal depends on WHERE it
 * stands, so those keys are renamed by their place in the parsed file (see
 * MODAL_TARGET_PLACES) and the matching source line is rewritten in place —
 * a `route:` of a navigation entry or a tab, the `component:` of an embedded
 * list, a widget, a quick-menu button or a numbered tab are never touched.
 * Keys inside a flow collection are left to the linter.
 *
 * `noerd:update` runs it over the project's app-configs and the configs of its
 * local modules, every `noerd:update-{module}` over the files that module
 * publishes and its own source; `noerd:lint` reports a file that still carries
 * an old name.
 *
 * @internal
 */
final class YamlVocabularyMigrator
{
    /**
     * Column types that were renamed; the value of a `type:` key.
     *
     * @var array<string, string>
     */
    public const COLUMN_TYPES = [
        'badge_with_text' => 'badgeWithText',
        'colored_text' => 'coloredText',
        'relation_link' => 'relationLink',
        'inversebool' => 'boolCheck',
        'inverse_bool' => 'boolCheck',
        'inverseBool' => 'boolCheck',
    ];

    /**
     * Column types that were renamed in list configs only — the name means
     * something else in other files (a validation rule, a form field).
     *
     * @var array<string, string>
     */
    public const LIST_COLUMN_TYPES = [
        'boolean' => 'bool',
        // The earlier spelling of the header filter types (`filters:`).
        'Picklist' => 'picklist',
        'ShowFrom' => 'dateFrom',
        'ShowUntil' => 'dateUntil',
    ];

    /**
     * List column types that were removed without a replacement the migrator
     * could choose — the linter reports them, a person decides.
     *
     * @var array<string, string> type => what to use instead
     */
    public const REMOVED_LIST_COLUMN_TYPES = [
        'id' => 'text (a row click opens the record)',
    ];

    /**
     * Keys that were renamed.
     *
     * @var array<string, string>
     */
    public const KEYS = [
        'block_menus' => 'blockMenus',
    ];

    /**
     * Navigation keys that were removed without a replacement — reported by the
     * linter, never rewritten.
     *
     * @var array<string, string> key => what to do instead
     */
    public const REMOVED_NAVIGATION_KEYS = [
        'sub_menu' => 'list the entries in a block of `blockMenus` — mark the block `submenu: true` to show it as a sub-navigation',
    ];

    /**
     * The places where an earlier `route:` / `component:` / `newRoute:` /
     * `newComponent:` opened a modal, per kind of file (fileKind()): place =>
     * [old key => new key]. A place is the path of a mapping with `*` for every
     * entry of a sequence; `**` stands for every mapping of a navigation file.
     * Tabs are handled apart (MODAL_TAB_KEYS).
     *
     * @var array<string, array<string, array<string, string>>>
     */
    public const MODAL_TARGET_PLACES = [
        'list' => [
            'actions.*' => ['route' => 'modalRoute'],
            'columns.*' => ['route' => 'modalRoute'],
            'columns.*.actions.*' => ['route' => 'modalRoute'],
        ],
        'layout' => [
            'actions.*' => ['route' => 'modalRoute'],
            'relations.*' => ['route' => 'modalRoute', 'component' => 'modalComponent'],
            'widgets.*' => ['route' => 'showMoreModalRoute'],
        ],
        'navigation' => [
            '**' => ['component' => 'modalComponent', 'newRoute' => 'newModalRoute', 'newComponent' => 'newModalComponent'],
        ],
    ];

    /**
     * The `component:` of a tab that opens it as a modal — a tab without
     * `number:` or with a `modalRoute:`. A numbered tab is an inline panel whose
     * `component:` (a module's own convention) stays.
     *
     * @var array<string, string>
     */
    public const MODAL_TAB_KEYS = ['component' => 'modalComponent'];

    private const BOM = "\xEF\xBB\xBF";

    private const PATH_SEPARATOR = "\x1F";

    /**
     * Whether a path (relative to an app-configs directory) is a list config:
     * `{app}/lists/…`.
     */
    public static function isListConfig(string $relativePath): bool
    {
        $segments = explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $relativePath));

        return in_array('lists', array_slice($segments, 0, -1), true);
    }

    /**
     * The kind of a config by its path relative to an app-configs directory:
     * `list` (`{app}/lists/…`), `layout` (`details/`, `pages/`, `settings/`),
     * `navigation` (`navigation*.yml`) or null for every other file
     * (quick menu, dashboard widgets, setup collections …).
     */
    public static function fileKind(string $relativePath): ?string
    {
        $segments = explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $relativePath));
        $folders = array_slice($segments, 0, -1);

        if (str_starts_with((string) end($segments), 'navigation')) {
            return 'navigation';
        }

        if (in_array('lists', $folders, true)) {
            return 'list';
        }

        if (array_intersect(['details', 'pages', 'settings'], $folders) !== []) {
            return 'layout';
        }

        return null;
    }

    /**
     * Migrate every YAML file below the given directories (or the given files).
     *
     * @param  list<string>  $paths  directories or single YAML files
     * @return list<string> the files that were (or, in a dry run, would be) rewritten
     */
    public function migrate(array $paths, bool $dryRun = false): array
    {
        $changed = [];

        foreach ($this->yamlFiles($paths) as $path => $relative) {
            $source = (string) file_get_contents($path);
            $migrated = $this->migrateSource($source, self::isListConfig($relative), self::fileKind($relative));

            if ($migrated === $source) {
                continue;
            }

            if (! $dryRun) {
                file_put_contents($path, $migrated);
            }

            $changed[] = $path;
        }

        return array_values(array_unique($changed));
    }

    /**
     * The source of one YAML file in the current vocabulary. $kind (fileKind())
     * decides which modal target keys are renamed; a list config without a kind
     * is treated as `list`.
     */
    public function migrateSource(string $source, bool $listConfig = false, ?string $kind = null): string
    {
        $types = self::typesFor($listConfig);
        $kind ??= $listConfig ? 'list' : null;
        $bom = str_starts_with($source, self::BOM) ? self::BOM : '';
        $lines = preg_split('/(?<=\n)/', mb_substr($source, $bom === '' ? 0 : 1)) ?: [];

        foreach ($this->configurationLines($lines) as $index => $line) {
            $lines[$index] = $this->migrateLine($line, $types);
        }

        if ($kind !== null) {
            $lines = $this->migrateModalTargets($lines, $kind);
        }

        return $bom . implode('', $lines);
    }

    /**
     * The modal target keys a source still carries in an earlier meaning (for
     * the linter), e.g. "`route:` at actions.0 (now `modalRoute:`)". Besides
     * the places the migrator rewrites it reports every `newRoute:` /
     * `newComponent:` and, outside navigation files, every mapping holding
     * `route:` AND `modalComponent:` — the earlier spelling of a route modal
     * with its fallback, wherever it stands.
     *
     * @return list<string>
     */
    public function outdatedModalTargets(string $source, ?string $kind): array
    {
        $config = $this->parse(str_starts_with($source, self::BOM) ? mb_substr($source, 1) : $source);

        if (! is_array($config)) {
            return [];
        }

        $found = [];

        foreach ($kind === null ? [] : self::modalTargetRenames($config, $kind) as $path => $newKey) {
            $segments = explode(self::PATH_SEPARATOR, $path);
            $oldKey = array_pop($segments);
            $found[] = "`{$oldKey}:` at " . ($segments === [] ? 'the top level' : implode('.', $segments)) . " (now `{$newKey}:`)";
        }

        $walk = function (array $node, array $path) use (&$walk, &$found, $kind): void {
            if (! array_is_list($node)) {
                $at = $path === [] ? 'the top level' : implode('.', $path);

                foreach (['newRoute' => 'newModalRoute', 'newComponent' => 'newModalComponent'] as $old => $new) {
                    if (array_key_exists($old, $node)) {
                        $found[] = "`{$old}:` at {$at} (now `{$new}:`)";
                    }
                }

                if ($kind !== 'navigation' && array_key_exists('route', $node) && array_key_exists('modalComponent', $node)) {
                    $found[] = "`route:` next to `modalComponent:` at {$at} (now `modalRoute:`)";
                }
            }

            foreach ($node as $key => $child) {
                if (is_array($child)) {
                    $walk($child, [...$path, (string) $key]);
                }
            }
        };
        $walk($config, []);

        return array_values(array_unique($found));
    }

    /**
     * The old names a source still carries (for the linter).
     *
     * @return list<string>
     */
    public function outdatedNames(string $source, bool $listConfig = false): array
    {
        $types = self::typesFor($listConfig);
        $found = [];
        $source = str_starts_with($source, self::BOM) ? mb_substr($source, 1) : $source;

        foreach ($this->configurationLines(preg_split('/(?<=\n)/', $source) ?: []) as $line) {
            $flow = self::isFlowLine($line);

            foreach ($flow ? [self::typePattern($types), self::flowTypePattern($types)] : [self::typePattern($types)] as $pattern) {
                if (preg_match_all($pattern, $line, $matches)) {
                    foreach ($matches['type'] as $type) {
                        $found[] = 'type: ' . $type;
                    }
                }
            }

            foreach ($flow ? [self::keyPattern(), self::flowKeyPattern()] : [self::keyPattern()] as $pattern) {
                if (preg_match_all($pattern, $line, $matches)) {
                    foreach ($matches['key'] as $key) {
                        $found[] = $key . ':';
                    }
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @return array<string, string>
     */
    private static function typesFor(bool $listConfig): array
    {
        return $listConfig ? [...self::COLUMN_TYPES, ...self::LIST_COLUMN_TYPES] : self::COLUMN_TYPES;
    }

    /**
     * `type: colored_text`, optionally quoted (value and key), as a mapping
     * entry or the first key of a list item, with an optional trailing comment.
     *
     * @param  array<string, string>  $types
     */
    private static function typePattern(array $types): string
    {
        return '/^(?<head>\s*(?:-\s+)?(?<kq>[\'"]?)type\k<kq>\s*:\s*)(?<quote>[\'"]?)(?<type>' . self::alternatives($types) . ')\k<quote>(?<tail>\s*(?:#.*)?\R?)$/';
    }

    /**
     * `type: colored_text` inside a flow mapping (`{field: a, type: colored_text}`).
     *
     * @param  array<string, string>  $types
     */
    private static function flowTypePattern(array $types): string
    {
        return '/(?<head>[{,]\s*(?<kq>[\'"]?)type\k<kq>\s*:\s*)(?<quote>[\'"]?)(?<type>' . self::alternatives($types) . ')\k<quote>(?=\s*[,}])/';
    }

    /**
     * Whether the VALUE of a line is a flow collection (`- {…}`, `key: [{…}]`) —
     * never a quoted text that merely contains a brace.
     */
    private static function isFlowLine(string $line): bool
    {
        return preg_match('/^\s*(?:-\s+)*(?:(?:"[^"]*"|\'[^\']*\'|[^\s#\'"{\[][^#:]*?)\s*:\s*)?[{\[]/', $line) === 1;
    }

    private static function keyPattern(): string
    {
        return '/^(?<head>\s*(?:-\s+)?)(?<kq>[\'"]?)(?<key>' . self::alternatives(self::KEYS) . ')\k<kq>(?<tail>\s*:.*\R?)$/s';
    }

    private static function flowKeyPattern(): string
    {
        return '/(?<head>[{,]\s*)(?<kq>[\'"]?)(?<key>' . self::alternatives(self::KEYS) . ')\k<kq>(?=\s*:)/';
    }

    /**
     * @param  array<string, string>  $names
     */
    private static function alternatives(array $names): string
    {
        return implode('|', array_map(static fn(string $name): string => preg_quote($name, '/'), array_keys($names)));
    }

    /**
     * The modal target keys to rename: key path (PATH_SEPARATOR-joined) => new key.
     * A key is renamed only while its new name is not taken in the same mapping.
     *
     * @param  array<mixed>  $config
     * @return array<string, string>
     */
    private static function modalTargetRenames(array $config, string $kind): array
    {
        $renames = [];

        $rename = static function (mixed $mapping, array $path, array $keys) use (&$renames): void {
            if (! is_array($mapping) || array_is_list($mapping) && $mapping !== []) {
                return;
            }

            foreach ($keys as $old => $new) {
                if (array_key_exists($old, $mapping) && ! array_key_exists($new, $mapping)) {
                    $renames[implode(self::PATH_SEPARATOR, [...$path, $old])] = $new;
                }
            }
        };

        if ($kind === 'navigation') {
            $walk = static function (array $node, array $path) use (&$walk, $rename): void {
                if (! array_is_list($node)) {
                    $rename($node, $path, self::MODAL_TARGET_PLACES['navigation']['**']);
                }

                foreach ($node as $key => $child) {
                    if (is_array($child)) {
                        $walk($child, [...$path, (string) $key]);
                    }
                }
            };
            $walk($config, []);

            return $renames;
        }

        foreach (self::MODAL_TARGET_PLACES[$kind] ?? [] as $place => $keys) {
            foreach (self::mappingsAt($config, explode('.', $place), []) as [$path, $mapping]) {
                $rename($mapping, $path, $keys);
            }
        }

        if ($kind === 'list' || $kind === 'layout') {
            foreach (self::mappingsAt($config, ['tabs', '*'], []) as [$path, $tab]) {
                if (is_array($tab) && (! array_key_exists('number', $tab) || array_key_exists('modalRoute', $tab))) {
                    $rename($tab, $path, self::MODAL_TAB_KEYS);
                }
            }
        }

        return $renames;
    }

    /**
     * The mappings at a place (`actions.*`): [path, mapping] pairs.
     *
     * @param  list<string>  $place
     * @param  list<string>  $path
     * @return list<array{0: list<string>, 1: mixed}>
     */
    private static function mappingsAt(mixed $node, array $place, array $path): array
    {
        if ($place === []) {
            return [[$path, $node]];
        }

        if (! is_array($node)) {
            return [];
        }

        $segment = array_shift($place);
        $found = [];

        if ($segment === '*') {
            if (! array_is_list($node)) {
                return [];
            }

            foreach ($node as $index => $child) {
                $found = [...$found, ...self::mappingsAt($child, $place, [...$path, (string) $index])];
            }

            return $found;
        }

        return array_key_exists($segment, $node) ? self::mappingsAt($node[$segment], $place, [...$path, $segment]) : [];
    }

    /**
     * How many flow collections (`{`, `[`) a line opens and leaves open —
     * outside quotes and comments.
     */
    private static function flowBalance(string $text): int
    {
        $text = (string) preg_replace('/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\']|\'\')*\'/', '""', $text);
        $text = (string) preg_replace('/(^|\s)#.*$/', '', $text);

        return mb_substr_count($text, '{') + mb_substr_count($text, '[') - mb_substr_count($text, '}') - mb_substr_count($text, ']');
    }

    /**
     * The YAML files below the paths, path => path relative to its app-configs
     * root (what isListConfig() needs).
     *
     * @param  list<string>  $paths
     * @return array<string, string>
     */
    private function yamlFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                if (in_array(pathinfo($path, PATHINFO_EXTENSION), ['yml', 'yaml'], true)) {
                    $normalized = str_replace(DIRECTORY_SEPARATOR, '/', $path);
                    $position = mb_strrpos($normalized, '/app-configs/');
                    $files[$path] = $position === false ? basename($path) : mb_substr($normalized, $position + mb_strlen('/app-configs/'));
                }

                continue;
            }

            if (! is_dir($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if (in_array($file->getExtension(), ['yml', 'yaml'], true)) {
                    $files[$file->getPathname()] = $file->getRelativePathname();
                }
            }
        }

        return $files;
    }

    /**
     * The lines that carry configuration — every line except the content of a
     * block scalar (`key: |`, `key: >-`, `- |`), which is text.
     *
     * @param  list<string>  $lines
     * @return array<int, string>
     */
    private function configurationLines(array $lines): array
    {
        $configuration = [];
        $scalarIndent = null;

        foreach ($lines as $index => $line) {
            $content = mb_rtrim($line, "\r\n");

            if ($scalarIndent !== null) {
                $indent = mb_strlen($content) - mb_strlen(mb_ltrim($content, " \t"));

                if (mb_trim($content) === '' || $indent > $scalarIndent) {
                    continue;
                }

                $scalarIndent = null;
            }

            $configuration[$index] = $line;

            // `key: |` or `- key: >-` (the key's column is the parent indent), `- |`.
            if (preg_match('/^(?<lead>\s*(?:-\s+)*)(?<rest>.*?)[|>][-+0-9]*\s*(?:#.*)?$/', $content, $match)
                && preg_match('/^(?:(?:"[^"]*"|\'[^\']*\'|[^\s#\'"][^#]*?)\s*:\s*)?$/', $match['rest'])
                && ($match['rest'] !== '' || str_contains($match['lead'], '-'))) {
                // The content of `key: |` is indented deeper than the key, that of `- |` deeper than the dash.
                $scalarIndent = $match['rest'] === ''
                    ? (int) mb_strrpos($match['lead'], '-')
                    : mb_strlen($match['lead']);
            }
        }

        return $configuration;
    }

    /**
     * @param  array<string, string>  $types
     */
    private function migrateLine(string $line, array $types): string
    {
        $renameType = static fn(array $match): string => $match['head'] . $match['quote'] . $types[$match['type']] . $match['quote'] . ($match['tail'] ?? '');
        $renameKey = static fn(array $match): string => $match['head'] . $match['kq'] . self::KEYS[$match['key']] . $match['kq'] . ($match['tail'] ?? '');

        $line = (string) preg_replace_callback(self::typePattern($types), $renameType, $line);
        $line = (string) preg_replace_callback(self::keyPattern(), $renameKey, $line);

        if (self::isFlowLine($line)) {
            $line = (string) preg_replace_callback(self::flowTypePattern($types), $renameType, $line);
            $line = (string) preg_replace_callback(self::flowKeyPattern(), $renameKey, $line);
        }

        return $line;
    }

    /**
     * Rename the modal target keys of a file: the PARSED file names the keys to
     * rename (modalTargetRenames()), the line scan (keyLines()) finds the line
     * of each and rewrites the key there — comments, quotes and formatting stay.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function migrateModalTargets(array $lines, string $kind): array
    {
        $config = $this->parse(implode('', $lines));

        if (! is_array($config)) {
            return $lines;
        }

        $renames = self::modalTargetRenames($config, $kind);

        if ($renames === []) {
            return $lines;
        }

        foreach ($this->keyLines($lines) as $index => $key) {
            $newKey = $renames[$key['path']] ?? null;

            if ($newKey === null) {
                continue;
            }

            $quote = in_array($key['token'][0], ['"', "'"], true) ? $key['token'][0] : '';
            $lines[$index] = mb_substr($lines[$index], 0, $key['offset']) . $quote . $newKey . $quote
                . mb_substr($lines[$index], $key['offset'] + mb_strlen($key['token']));
        }

        return $lines;
    }

    /**
     * Every line that holds a mapping key in block style, with the key's path
     * (PATH_SEPARATOR-joined, sequence entries as their index — the shape the
     * parser gives), its byte offset in the line and its source token (quotes
     * included). Lines inside a flow collection or a block scalar are skipped.
     *
     * @param  list<string>  $lines
     * @return array<int, array{path: string, offset: int, token: string}>
     */
    private function keyLines(array $lines): array
    {
        /** @var list<array{indent: int, segment: string, id: int}> $frames */
        $frames = [];
        $sequenceIndexes = [];
        $nextId = 1;
        $flowDepth = 0;
        $keys = [];

        foreach ($this->configurationLines($lines) as $index => $line) {
            $content = mb_rtrim($line, "\r\n");

            if ($flowDepth > 0) {
                $flowDepth = max(0, $flowDepth + self::flowBalance($content));

                continue;
            }

            $trimmed = mb_ltrim($content, ' ');

            if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '---') || str_starts_with($trimmed, '...')) {
                continue;
            }

            $column = mb_strlen($content) - mb_strlen($trimmed);
            $rest = $trimmed;

            // `- ` opens a sequence entry (possibly several on one line: `- - a`).
            while (preg_match('/^-(\s+|$)/', $rest, $dash)) {
                while ($frames !== [] && ($frames[array_key_last($frames)]['indent'] > $column
                    || $frames[array_key_last($frames)]['indent'] === $column && $frames[array_key_last($frames)]['segment'][0] === '#')) {
                    array_pop($frames);
                }

                $parent = $frames === [] ? 0 : $frames[array_key_last($frames)]['id'];
                $counter = $parent . ':' . $column;
                $sequenceIndexes[$counter] = ($sequenceIndexes[$counter] ?? -1) + 1;
                $frames[] = ['indent' => $column, 'segment' => '#' . $sequenceIndexes[$counter], 'id' => $nextId++];

                $column += mb_strlen($dash[0]);
                $rest = mb_substr($rest, mb_strlen($dash[0]));
            }

            if (! preg_match('/^(?<token>"[^"]*"|\'[^\']*\'|[A-Za-z0-9_$][A-Za-z0-9_$.\/-]*)[ \t]*:(?=[ \t]|$)(?<value>.*)$/', $rest, $match)) {
                $flowDepth = self::flowBalance($rest);

                continue;
            }

            while ($frames !== [] && $frames[array_key_last($frames)]['indent'] >= $column) {
                array_pop($frames);
            }

            $key = in_array($match['token'][0], ['"', "'"], true) ? mb_substr($match['token'], 1, -1) : $match['token'];
            $frames[] = ['indent' => $column, 'segment' => $key, 'id' => $nextId++];

            $keys[$index] = [
                'path' => implode(self::PATH_SEPARATOR, array_map(
                    static fn(array $frame): string => $frame['segment'][0] === '#' ? mb_substr($frame['segment'], 1) : $frame['segment'],
                    $frames,
                )),
                'offset' => $column,
                'token' => $match['token'],
            ];

            $flowDepth = self::flowBalance($match['value']);
        }

        return $keys;
    }

    private function parse(string $source): mixed
    {
        try {
            return Yaml::parse($source);
        } catch (ParseException) {
            return null;
        }
    }
}
