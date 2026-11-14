<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Noerd\Services\PicklistRegistry;
use Noerd\Support\ConfigLinter;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | noerd:lint checks app-configs YAML against the registries and routes.
 | Fixture YAMLs are written at runtime into a throwaway directory — never
 | the shipped configuration.
 */

beforeEach(function (): void {
    $this->lintDir = sys_get_temp_dir() . '/noerd-lint-' . uniqid();
    File::ensureDirectoryExists($this->lintDir . '/zzlint/details');
    File::ensureDirectoryExists($this->lintDir . '/zzlint/lists');

    // The command always lints the application's own app-configs as well: a
    // throwaway base path keeps the host's configuration (and its findings)
    // out of these assertions.
    $this->originalBasePath = $this->app->basePath();
    $this->app->setBasePath($this->lintDir);

    app(PicklistRegistry::class)->register('zzLintOptions', fn(): array => []);
});

afterEach(function (): void {
    $this->app->setBasePath($this->originalBasePath);
    File::deleteDirectory($this->lintDir);
});

function zzLintWrite(string $dir, string $relative, string $yaml): void
{
    File::put($dir . '/' . $relative, $yaml);
}

function zzLintIssues(string $dir): array
{
    return app(ConfigLinter::class)->lint([$dir]);
}

it('reports nothing for a valid layout', function (): void {
    zzLintWrite($this->lintDir, 'zzlint/details/zz-detail.yml', <<<'YAML'
title: Zz
fields:
  - name: detailData.name
    type: text
  - name: detailData.status
    type: select
    picklistField: zzLintOptions
  - type: block
    fields:
      - name: detailData.note
        type: textarea
actions: []
YAML);

    expect(zzLintIssues($this->lintDir))->toBe([]);
});

it('reports a name of an earlier vocabulary as an error', function (): void {
    zzLintWrite($this->lintDir, 'zzlint/lists/zz-list.yml', <<<'YAML'
title: Zz
columns:
  - field: state
    type: colored_text
  - field: active
    type: boolean
YAML);

    $messages = array_column(zzLintIssues($this->lintDir), 'message');

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toContain('`type: colored_text`')->toContain('noerd:update')
        ->and($messages[1])->toContain('`type: boolean`');
});

it('reports render-breaking mistakes as errors', function (): void {
    zzLintWrite($this->lintDir, 'zzlint/details/zz-detail.yml', <<<'YAML'
fields:
  - name: detailData.owner_id
    type: zzUnknownRelation
  - type: text
    label: No name
actions:
  - label: Open
    modalRoute: zz.not.registered
YAML);
    zzLintWrite($this->lintDir, 'zzlint/lists/zz-list.yml', <<<'YAML'
columns:
  - label: Missing field
YAML);
    zzLintWrite($this->lintDir, 'zzlint/details/broken.yml', "fields:\n  - name: [unclosed\n");

    $errors = collect(zzLintIssues($this->lintDir))->where('severity', ConfigLinter::ERROR)->pluck('message')->implode("\n");

    expect($errors)->toContain('`zzUnknownRelation` is not registered')
        ->toContain('has no `name`')
        ->toContain('`zz.not.registered` is not registered and the action has no modalComponent fallback')
        ->toContain('The column has no `field`')
        ->toContain('cannot be parsed');
});

it('reports likely mistakes as warnings', function (): void {
    zzLintWrite($this->lintDir, 'zzlint/details/zz-detail.yml', <<<'YAML'
fields:
  - name: detailData.when
    type: datetime
  - name: detailData.kind
    type: select
    picklistField: zzNobodyDefinesThis
  - name: detailData.code
    type: text
    readonly: true
    required: true
  - name: detailData.tags
    type: text
    options: [a, b]
actions:
  - label: Open
    modalRoute: zz.not.registered
    modalComponent: zz::fallback
YAML);

    $warnings = collect(zzLintIssues($this->lintDir))->where('severity', ConfigLinter::WARNING)->pluck('message')->implode("\n");

    expect($warnings)->toContain('`datetime` is not registered')
        ->toContain('picklistField: zzNobodyDefinesThis` is neither a registered picklist provider')
        ->toContain('readonly AND required')
        ->toContain('Flow-style YAML')
        ->toContain('the modalComponent fallback opens instead');
});

it('fails the command on errors, and on warnings only with --strict', function (): void {
    zzLintWrite($this->lintDir, 'zzlint/details/zz-detail.yml', <<<'YAML'
fields:
  - name: detailData.when
    type: datetime
YAML);

    $this->artisan('noerd:lint', ['--path' => [$this->lintDir]])->assertExitCode(0);
    $this->artisan('noerd:lint', ['--path' => [$this->lintDir], '--strict' => true])->assertExitCode(1);

    zzLintWrite($this->lintDir, 'zzlint/details/zz-other-detail.yml', <<<'YAML'
fields:
  - name: detailData.owner_id
    type: zzUnknownRelation
YAML);

    $this->artisan('noerd:lint', ['--path' => [$this->lintDir]])->assertExitCode(1);
});

/*
 | The parsed structure is the authority for list column and filter types and
 | for navigation keys — a spelling the line patterns cannot see is reported all
 | the same.
 */
describe('parsed structure', function (): void {
    function zzLintErrors(string $dir): string
    {
        return collect(zzLintIssues($dir))->where('severity', ConfigLinter::ERROR)->pluck('message')->implode("\n");
    }

    it('reports old and unknown column types in flow style, under quoted keys and in CRLF files', function (): void {
        zzLintWrite($this->lintDir, 'zzlint/lists/zz-list.yml', "columns:\r\n  - {field: a, type: colored_text}\r\n  - field: b\r\n    \"type\": inverseBool\r\n  - field: c\r\n    type:\tboolean\r\n  - field: d\r\n    type: zzNoSuchType\r\n  - field: e\r\n    type: id\r\n  - field: f\r\n    type: boolCheck\r\n");

        $errors = zzLintErrors($this->lintDir);

        expect($errors)->toContain('`type: colored_text`')
            ->toContain('`type: inverseBool`')
            ->toContain('`type: boolean`')
            ->toContain('The column type `zzNoSuchType` is unknown')
            ->toContain('The column type `id` was removed')
            ->not->toContain('`boolCheck`');

        // Every old name once, although both the line check and the parsed check see it.
        expect(mb_substr_count($errors, '`type: colored_text`'))->toBe(1);
    });

    it('reports an unknown filter type', function (): void {
        zzLintWrite($this->lintDir, 'zzlint/lists/zz-list.yml', "filters:\n  - label: Year\n    column: year\n    type: zzYearly\n  - label: From\n    column: date\n    type: dateFrom\n");

        expect(zzLintErrors($this->lintDir))->toContain('The filter type `zzYearly` is unknown')
            ->not->toContain('`dateFrom`');
    });

    it('reports renamed and removed navigation keys wherever they sit', function (): void {
        zzLintWrite($this->lintDir, 'zzlint/navigation.yml', "- {title: Zz, block_menus: [{title: Block, navigations: [{title: Item, route: zz, sub_menu: []}]}]}\n");

        expect(zzLintErrors($this->lintDir))->toContain('`block_menus:`')
            ->toContain('The navigation key `sub_menu` was removed');
    });

    it('reports a BOM and still reads the rest of the file', function (): void {
        zzLintWrite($this->lintDir, 'zzlint/lists/zz-list.yml', "\xEF\xBB\xBFcolumns:\n  - field: a\n    type: colored_text\n");

        expect(zzLintErrors($this->lintDir))->toContain('UTF-8 BOM')
            ->toContain('`type: colored_text`');
    });

    it('never reports the text of a block scalar', function (): void {
        zzLintWrite($this->lintDir, 'zzlint/lists/zz-list.yml', "description: |\n  type: colored_text\ncolumns:\n  - field: a\n    type: text\n");

        expect(zzLintIssues($this->lintDir))->toBe([]);
    });

    it('lints the app-configs of the local modules by default', function (): void {
        File::ensureDirectoryExists($this->lintDir . '/app-modules/zzmod/app-configs/zzmod/lists');
        File::put($this->lintDir . '/app-modules/zzmod/app-configs/zzmod/lists/zz-list.yml', "columns:\n  - field: a\n    type: colored_text\n");

        $this->artisan('noerd:lint')->expectsOutputToContain('colored_text')->assertExitCode(1);
        $this->artisan('noerd:lint', ['--project-only' => true])->assertExitCode(0);
    });
});

/*
 | A modal is opened by modalRoute:/modalComponent: only. A key that opened a
 | modal in the earlier vocabulary is an error with the noerd:update hint —
 | wherever it stands, flow style included — while the same key in a place
 | where it navigates or renders inline is valid.
 */
describe('modal targets', function (): void {
    it('reports every modal target of the earlier vocabulary', function (): void {
        zzLintWrite($this->lintDir, 'zzlint/lists/zz-list.yml', "actions:\n  - label: New\n    route: zz.detail\ncolumns:\n  - {field: party, type: relationLink, route: zz.party, modalComponent: zz::party}\n");
        zzLintWrite($this->lintDir, 'zzlint/details/zz-detail.yml', "actions:\n  - label: History\n    route: zz.history\ntabs:\n  - label: Invoices\n    component: zz::invoices-list\nrelations:\n  - label: Orders\n    component: zz::orders-list\nwidgets:\n  - component: zz::open-list\n    route: zz.open\n");
        zzLintWrite($this->lintDir, 'zzlint/navigation.yml', "- title: Zz\n  route: zz\n  blockMenus:\n    - title: Block\n      navigations:\n        - title: Items\n          route: zz.items\n          newRoute: zz.item.detail\n          newComponent: zz::item-detail\n        - title: Wizard\n          component: zz::wizard-modal\n");

        $errors = collect(zzLintIssues($this->lintDir))->where('severity', ConfigLinter::ERROR)->pluck('message')->implode("\n");

        expect($errors)->toContain('`route:` at actions.0 (now `modalRoute:`)')
            ->toContain('`route:` at columns.0 (now `modalRoute:`)')
            ->toContain('`component:` at tabs.0 (now `modalComponent:`)')
            ->toContain('`component:` at relations.0 (now `modalComponent:`)')
            ->toContain('`route:` at widgets.0 (now `showMoreModalRoute:`)')
            ->toContain('`newRoute:` at 0.blockMenus.0.navigations.0 (now `newModalRoute:`)')
            ->toContain('`newComponent:` at 0.blockMenus.0.navigations.0 (now `newModalComponent:`)')
            ->toContain('`component:` at 0.blockMenus.0.navigations.1 (now `modalComponent:`)')
            ->toContain('noerd:update');
    });

    it('accepts route: where it navigates and component: where it renders inline', function (): void {
        zzLintWrite($this->lintDir, 'zzlint/details/zz-detail.yml', "tabs:\n  - number: 1\n    label: Data\n  - number: 2\n    label: Inline\n    component: zz::inline\n  - label: Page\n    route: zz.page\nlists:\n  - component: zz::embedded-list\nwidgets:\n  - component: zz::open-list\n    showMoreModalRoute: zz.open\n    showMoreModalComponent: zz::open-list\n");
        zzLintWrite($this->lintDir, 'zzlint/navigation.yml', "- title: Zz\n  route: zz\n  blockMenus:\n    - title: Block\n      navigations:\n        - title: Items\n          route: zz.items\n          newModalRoute: zz.item.detail\n          newModalComponent: zz::item-detail\n        - title: Wizard\n          modalComponent: zz::wizard-modal\n");

        expect(collect(zzLintIssues($this->lintDir))->pluck('message')->implode("\n"))->not->toContain('modal target');
    });
});
