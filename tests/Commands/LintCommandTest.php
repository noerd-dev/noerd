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

it('reports render-breaking mistakes as errors', function (): void {
    zzLintWrite($this->lintDir, 'zzlint/details/zz-detail.yml', <<<'YAML'
fields:
  - name: detailData.owner_id
    type: zzUnknownRelation
  - type: text
    label: No name
actions:
  - label: Open
    route: zz.not.registered
YAML);
    zzLintWrite($this->lintDir, 'zzlint/lists/zz-list.yml', <<<'YAML'
columns:
  - label: Missing field
YAML);
    zzLintWrite($this->lintDir, 'zzlint/details/broken.yml', "fields:\n  - name: [unclosed\n");

    $errors = collect(zzLintIssues($this->lintDir))->where('severity', ConfigLinter::ERROR)->pluck('message')->implode("\n");

    expect($errors)->toContain('`zzUnknownRelation` is not registered')
        ->toContain('has no `name`')
        ->toContain('`zz.not.registered` is not registered and the action has no fallback')
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
    route: zz.not.registered
    modalComponent: zz::fallback
YAML);

    $warnings = collect(zzLintIssues($this->lintDir))->where('severity', ConfigLinter::WARNING)->pluck('message')->implode("\n");

    expect($warnings)->toContain('`datetime` is not registered')
        ->toContain('picklistField: zzNobodyDefinesThis` is neither a registered picklist provider')
        ->toContain('readonly AND required')
        ->toContain('Flow-style YAML')
        ->toContain('the fallback opens instead');
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
