<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Noerd\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

uses(TestCase::class);

/**
 * Laravel Boost renders a package's guideline through Blade and silently DROPS
 * a guideline that fails to render (RenderFailures). A skill is only picked up
 * when its SKILL.md front matter carries `name` + `description`. These tests
 * guard the mechanics — the rule texts themselves are content, not asserted.
 */
beforeEach(function (): void {
    $this->boostPath = dirname(__DIR__, 2) . '/resources/boost';
});

it('renders the boost guideline through blade', function (): void {
    $source = File::get($this->boostPath . '/guidelines/core.blade.php');

    $rendered = Blade::render($source);

    // Only the render mechanics are asserted — the rule texts are content:
    // rendering must succeed non-empty, the @verbatim wrappers must be consumed
    // and the Blade literals of the examples must survive them.
    expect(mb_trim($rendered))->not->toBe('')
        ->and($rendered)
        ->toContain('{{')
        ->not->toContain('@verbatim')
        ->not->toContain('@endverbatim');
});

it('ships skills with a valid front matter', function (): void {
    $skillDirs = File::directories($this->boostPath . '/skills');

    expect($skillDirs)->not->toBeEmpty();

    foreach ($skillDirs as $dir) {
        $file = $dir . '/SKILL.md';

        expect(File::exists($file))->toBeTrue("Missing SKILL.md in {$dir}");

        $content = File::get($file);

        expect(preg_match('/^\s*---\s*\n(.*?)\n---\s*\n/s', $content, $matches))->toBe(1, "No front matter in {$file}");

        $frontmatter = Yaml::parse($matches[1]);

        expect($frontmatter)
            ->toHaveKey('name')
            ->toHaveKey('description')
            ->and($frontmatter['name'])->toBe(basename($dir))
            ->and($frontmatter['description'])->not->toBeEmpty();
    }
});
