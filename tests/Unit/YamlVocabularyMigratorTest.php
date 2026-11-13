<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Noerd\Support\YamlVocabularyMigrator;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('renames the column types and keeps comments, quotes and formatting', function (): void {
    $source = <<<'YAML'
    title: Orders
    columns:
      # the status
      - field: status
        type: colored_text
      - type: 'badge_with_text'   # first key of the item
        field: customer
      - field: link
        type: "relation_link"
      - field: hidden
        type: inversebool
    YAML;

    expect((new YamlVocabularyMigrator())->migrateSource($source))->toBe(<<<'YAML'
    title: Orders
    columns:
      # the status
      - field: status
        type: coloredText
      - type: 'badgeWithText'   # first key of the item
        field: customer
      - field: link
        type: "relationLink"
      - field: hidden
        type: inverseBool
    YAML);
});

it('renames the boolean column type in list configs only', function (): void {
    $source = "columns:\n  - field: active\n    type: boolean\n";
    $migrator = new YamlVocabularyMigrator();

    expect($migrator->migrateSource($source, listConfig: true))->toBe("columns:\n  - field: active\n    type: bool\n")
        ->and($migrator->migrateSource($source))->toBe($source);
});

it('renames the navigation key and leaves values that merely contain an old name alone', function (): void {
    $source = "- title: App\n  block_menus:\n    - title: 'About block_menus'\n      description: type: colored_text is gone\n";

    expect((new YamlVocabularyMigrator())->migrateSource($source))
        ->toBe("- title: App\n  blockMenus:\n    - title: 'About block_menus'\n      description: type: colored_text is gone\n");
});

it('changes nothing in a current file', function (): void {
    $source = "- title: App\n  blockMenus:\n    - title: Block\ncolumns:\n  - field: a\n    type: coloredText\n";
    $migrator = new YamlVocabularyMigrator();

    expect($migrator->migrateSource($source, listConfig: true))->toBe($source)
        ->and($migrator->outdatedNames($source, listConfig: true))->toBe([]);
});

it('rewrites the files of a directory once and reports them', function (): void {
    $directory = storage_path('framework/testing/zz-vocabulary-' . uniqid());
    File::ensureDirectoryExists($directory . '/zzapp/lists');
    File::put($directory . '/zzapp/lists/things-list.yml', "columns:\n  - field: a\n    type: boolean\n");
    File::put($directory . '/zzapp/navigation.yml', "- title: Zz\n  block_menus: []\n");
    File::put($directory . '/zzapp/lists/current-list.yml', "columns:\n  - field: a\n    type: bool\n");

    $migrator = new YamlVocabularyMigrator();

    expect($migrator->migrate([$directory], dryRun: true))->toHaveCount(2)
        ->and(File::get($directory . '/zzapp/navigation.yml'))->toContain('block_menus');

    expect($migrator->migrate([$directory]))->toHaveCount(2)
        ->and(File::get($directory . '/zzapp/navigation.yml'))->toBe("- title: Zz\n  blockMenus: []\n")
        ->and(File::get($directory . '/zzapp/lists/things-list.yml'))->toContain('type: bool')
        ->and($migrator->migrate([$directory]))->toBe([]);

    File::deleteDirectory($directory);
});

it('names what a file still carries', function (): void {
    expect((new YamlVocabularyMigrator())->outdatedNames("block_menus:\n  - type: colored_text\n"))
        ->toBe(['block_menus:', 'type: colored_text']);
});
