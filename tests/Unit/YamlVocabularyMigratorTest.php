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
        type: boolCheck
    YAML);
});

it('renames the boolean column type in list configs only', function (): void {
    $source = "columns:\n  - field: active\n    type: boolean\n";
    $migrator = new YamlVocabularyMigrator();

    expect($migrator->migrateSource($source, listConfig: true))->toBe("columns:\n  - field: active\n    type: bool\n")
        ->and($migrator->migrateSource($source))->toBe($source);
});

it('renames the earlier header filter types in list configs', function (): void {
    $source = "filters:\n  - column: show_from\n    type: ShowFrom\n  - column: show_until\n    type: ShowUntil\n  - column: status\n    type: Picklist\n";

    expect((new YamlVocabularyMigrator())->migrateSource($source, listConfig: true))
        ->toBe("filters:\n  - column: show_from\n    type: dateFrom\n  - column: show_until\n    type: dateUntil\n  - column: status\n    type: picklist\n");
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

it('renames every earlier spelling of the boolCheck column type', function (string $old): void {
    expect((new YamlVocabularyMigrator())->migrateSource("columns:\n  - field: a\n    type: {$old}\n", listConfig: true))
        ->toBe("columns:\n  - field: a\n    type: boolCheck\n");
})->with(['inversebool', 'inverse_bool', 'inverseBool']);

it('rewrites flow style, quoted keys and a space before the colon', function (): void {
    $migrator = new YamlVocabularyMigrator();
    $source = "columns:\n  - {field: a, type: colored_text}\n  - {\"type\": 'relation_link', field: b}\n  - field: c\n    \"type\": badge_with_text\n  - field: d\n    type : colored_text\n";

    expect($migrator->migrateSource($source, listConfig: true))->toBe(
        "columns:\n  - {field: a, type: coloredText}\n  - {\"type\": 'relationLink', field: b}\n  - field: c\n    \"type\": badgeWithText\n  - field: d\n    type : coloredText\n",
    )->and($migrator->outdatedNames($source, listConfig: true))->toBe(['type: colored_text', 'type: relation_link', 'type: badge_with_text']);

    expect($migrator->migrateSource("- title: App\n  'block_menus': []\n- {title: B, block_menus: []}\n"))
        ->toBe("- title: App\n  'blockMenus': []\n- {title: B, blockMenus: []}\n");
});

it('keeps a BOM, CRLF line endings and tabs after the colon', function (): void {
    $source = "\xEF\xBB\xBFcolumns:\r\n  - field: a\r\n    type:\tcolored_text\r\n";

    expect((new YamlVocabularyMigrator())->migrateSource($source))
        ->toBe("\xEF\xBB\xBFcolumns:\r\n  - field: a\r\n    type:\tcoloredText\r\n");

    $bomFirstLine = "\xEF\xBB\xBFblock_menus: []\n";
    expect((new YamlVocabularyMigrator())->migrateSource($bomFirstLine))->toBe("\xEF\xBB\xBFblockMenus: []\n");
});

it('never rewrites the content of a block scalar', function (): void {
    $source = <<<'YAML'
    description: |
      type: colored_text
      block_menus:
    columns:
      - field: a
        help: >-
          type: colored_text
        type: colored_text
      - |
        type: relation_link
      - field: b
        type: relation_link
    YAML;

    $migrated = (new YamlVocabularyMigrator())->migrateSource($source);

    expect($migrated)->toBe(<<<'YAML'
    description: |
      type: colored_text
      block_menus:
    columns:
      - field: a
        help: >-
          type: colored_text
        type: coloredText
      - |
        type: relation_link
      - field: b
        type: relationLink
    YAML)->and((new YamlVocabularyMigrator())->outdatedNames($migrated))->toBe([]);
});

it('leaves a quoted text that contains a brace alone', function (): void {
    $source = "columns:\n  - field: a\n    label: 'A {x, type: colored_text}'\n";

    expect((new YamlVocabularyMigrator())->migrateSource($source))->toBe($source);
});

it('migrates single files and tells list configs by their path', function (): void {
    $directory = storage_path('framework/testing/zz-vocabulary-files-' . uniqid());
    File::ensureDirectoryExists($directory . '/app-configs/zzapp/lists');
    File::put($directory . '/app-configs/zzapp/lists/a-list.yml', "columns:\n  - field: a\n    type: boolean\n");
    File::put($directory . '/app-configs/zzapp/other.yml', "columns:\n  - field: a\n    type: boolean\n");

    $changed = (new YamlVocabularyMigrator())->migrate([
        $directory . '/app-configs/zzapp/lists/a-list.yml',
        $directory . '/app-configs/zzapp/other.yml',
    ]);

    expect($changed)->toBe([$directory . '/app-configs/zzapp/lists/a-list.yml'])
        ->and(File::get($directory . '/app-configs/zzapp/other.yml'))->toContain('type: boolean');

    File::deleteDirectory($directory);
});

/*
 | Modal targets: which `route:` / `component:` opened a modal depends on the
 | place in the file, so the migrator renames them by their parsed path.
 */
describe('modal targets', function (): void {
    it('renames the modal targets of a list and keeps comments, quotes and formatting', function (): void {
        $source = <<<'YAML'
        title: Orders
        actions:
        # the create button
        - label: New Order
          route: zz.order.detail   # opens the detail
        - label: Import
          action: openImport
        columns:
          - field: party
            type: relationLink
            'route': zz.party.detail
            modalComponent: zz::party-detail
          - field: menu
            actions:
              - label: Edit
                route: zz.order.detail
        tabs:
          - label: Overview
            route: zz.overview
          - label: Invoices
            component: zz::invoices-list
        YAML;

        expect((new YamlVocabularyMigrator())->migrateSource($source, kind: 'list'))->toBe(<<<'YAML'
        title: Orders
        actions:
        # the create button
        - label: New Order
          modalRoute: zz.order.detail   # opens the detail
        - label: Import
          action: openImport
        columns:
          - field: party
            type: relationLink
            'modalRoute': zz.party.detail
            modalComponent: zz::party-detail
          - field: menu
            actions:
              - label: Edit
                modalRoute: zz.order.detail
        tabs:
          - label: Overview
            route: zz.overview
          - label: Invoices
            modalComponent: zz::invoices-list
        YAML);
    });

    it('renames actions, relation tiles, widgets and modal tabs of a detail or page, never an embedded list or a numbered tab', function (): void {
        $source = <<<'YAML'
        actions:
          - label: History
            route: zz.history
            modalComponent: noerd::audit-modal
        tabs:
          - number: 1
            label: Data
          - number: 2
            label: Availability
            component: zz::availability
          - label: Record
            modalRoute: zz.record
            component: zz::record-detail
        relations:
          - label: Orders
            route: zz.orders
            component: zz::orders-list
            arguments:
              partyId: $modelId
          - label: Notes
            component: zz::notes-list
        widgets:
          - title: Open
            component: zz::open-list
            route: zz.open
        lists:
          - title: Items
            component: zz::items-list
        fields:
          - name: detailData.route
            label: route
        YAML;

        expect((new YamlVocabularyMigrator())->migrateSource($source, kind: 'layout'))->toBe(<<<'YAML'
        actions:
          - label: History
            modalRoute: zz.history
            modalComponent: noerd::audit-modal
        tabs:
          - number: 1
            label: Data
          - number: 2
            label: Availability
            component: zz::availability
          - label: Record
            modalRoute: zz.record
            modalComponent: zz::record-detail
        relations:
          - label: Orders
            modalRoute: zz.orders
            modalComponent: zz::orders-list
            arguments:
              partyId: $modelId
          - label: Notes
            modalComponent: zz::notes-list
        widgets:
          - title: Open
            component: zz::open-list
            showMoreModalRoute: zz.open
        lists:
          - title: Items
            component: zz::items-list
        fields:
          - name: detailData.route
            label: route
        YAML);
    });

    it('renames the modal keys of a navigation and never its route', function (): void {
        $source = <<<'YAML'
        - title: Zz
          route: zz
          blockMenus:
            - title: Overview
              route: zz.dashboard
              navigations:
                - title: Items
                  route: zz.items
                  newRoute: zz.item.detail
                  newComponent: zz::item-detail
                - title: Record
                  modalRoute: zz.record
                  component: zz::record-detail
                - title: Wizard
                  component: zz::wizard-modal
        YAML;

        expect((new YamlVocabularyMigrator())->migrateSource($source, kind: 'navigation'))->toBe(<<<'YAML'
        - title: Zz
          route: zz
          blockMenus:
            - title: Overview
              route: zz.dashboard
              navigations:
                - title: Items
                  route: zz.items
                  newModalRoute: zz.item.detail
                  newModalComponent: zz::item-detail
                - title: Record
                  modalRoute: zz.record
                  modalComponent: zz::record-detail
                - title: Wizard
                  modalComponent: zz::wizard-modal
        YAML);
    });

    it('is idempotent and leaves a file in the current vocabulary unchanged', function (): void {
        $migrator = new YamlVocabularyMigrator();
        $old = "actions:\n  - label: New\n    route: zz.detail\nrelations:\n  - label: A\n    component: zz::a\n";
        $current = $migrator->migrateSource($old, kind: 'layout');

        expect($current)->not->toBe($old)
            ->and($migrator->migrateSource($current, kind: 'layout'))->toBe($current)
            ->and($migrator->outdatedModalTargets($current, 'layout'))->toBe([]);
    });

    it('never renames a key whose new name is already taken in the same entry', function (): void {
        $source = "actions:\n  - label: Both\n    route: zz.old\n    modalRoute: zz.new\n";

        expect((new YamlVocabularyMigrator())->migrateSource($source, kind: 'list'))->toBe($source);
    });

    it('leaves files without a modal place alone and a flow collection to the linter', function (): void {
        $migrator = new YamlVocabularyMigrator();
        $quickMenu = "buttons:\n  - component: zz::quick-button\n    apps:\n      - ZZ\n";
        $flow = "actions:\n  - {label: New, route: zz.detail}\n";

        expect($migrator->migrateSource($quickMenu))->toBe($quickMenu)
            ->and($migrator->migrateSource($flow, kind: 'list'))->toBe($flow)
            ->and($migrator->outdatedModalTargets($flow, 'list'))->toBe(['`route:` at actions.0 (now `modalRoute:`)']);
    });

    it('tells the place of a file by its path', function (string $relative, ?string $kind): void {
        expect(YamlVocabularyMigrator::fileKind($relative))->toBe($kind);
    })->with([
        ['zzapp/lists/zz-list.yml', 'list'],
        ['zzapp/details/zz-detail.yml', 'layout'],
        ['zzapp/details/sub/zz-detail.yml', 'layout'],
        ['zzapp/pages/zz-page.yml', 'layout'],
        ['zzapp/settings/zz-settings.yml', 'layout'],
        ['zzapp/navigation.yml', 'navigation'],
        ['setup/navigation.yml', 'navigation'],
        ['zzapp/navigation-members.yml', 'navigation'],
        ['quick-menu.yml', null],
        ['dashboard-widgets.yml', null],
        ['setup/collections/zz.yml', null],
    ]);

    it('migrates the modal targets of the files below a directory by their place', function (): void {
        $directory = storage_path('framework/testing/zz-modal-targets-' . uniqid());
        File::ensureDirectoryExists($directory . '/zzapp/lists');
        File::put($directory . '/zzapp/lists/a-list.yml', "actions:\n  - label: New\n    route: zz.detail\n");
        File::put($directory . '/zzapp/navigation.yml', "- title: Zz\n  route: zz\n  blockMenus:\n    - title: B\n      navigations:\n        - title: A\n          route: zz.a\n          newComponent: zz::a\n");
        File::put($directory . '/quick-menu.yml', "buttons:\n  - component: zz::button\n");

        $migrator = new YamlVocabularyMigrator();
        $wouldChange = $migrator->migrate([$directory], dryRun: true);

        expect($wouldChange)->toHaveCount(2)
            ->and(File::get($directory . '/zzapp/lists/a-list.yml'))->toContain('route: zz.detail');

        $migrator->migrate([$directory]);

        expect(File::get($directory . '/zzapp/lists/a-list.yml'))->toContain('modalRoute: zz.detail')
            ->and(File::get($directory . '/zzapp/navigation.yml'))->toContain("          route: zz.a\n          newModalComponent: zz::a")
            ->and(File::get($directory . '/quick-menu.yml'))->toBe("buttons:\n  - component: zz::button\n")
            ->and($migrator->migrate([$directory]))->toBe([]);

        File::deleteDirectory($directory);
    });
});
