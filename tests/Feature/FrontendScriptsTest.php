<?php

declare(strict_types=1);

use Noerd\Support\KeyboardShortcutHelper;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

uses(Noerd\Tests\TestCase::class);

/*
 | The pure parts of resources/js are proven by Node harnesses in tests/js/
 | (no browser, no DOM). Each harness exits non-zero on the first failure.
 */

function zzNodeBinary(): string
{
    $node = (new ExecutableFinder())->find('node');

    if ($node === null) {
        test()->markTestSkipped('node is not available.');
    }

    return $node;
}

/**
 * @return array{0: int, 1: string}
 */
function zzRunNode(array $arguments): array
{
    $process = new Process([zzNodeBinary(), ...$arguments], dirname(__DIR__, 2));
    $process->run();

    return [(int) $process->getExitCode(), $process->getErrorOutput() . $process->getOutput()];
}

it('passes the node harness', function (string $harness): void {
    [$exitCode, $output] = zzRunNode(['tests/js/' . $harness]);

    expect($exitCode)->toBe(0, $output);
})->with([
    'currency parser' => 'currency.mjs',
    'keyboard rules' => 'keyboard.mjs',
    'form values' => 'form-values.mjs',
]);

it('generates list shortcut conditions that follow the keyboard rules', function (): void {
    $modifierless = KeyboardShortcutHelper::parse('zz_letter', 'n')['js'];
    $withCtrl = KeyboardShortcutHelper::parse('zz_combo', 'ctrl+k')['js'];

    // The generated Alpine condition is evaluated against synthetic events;
    // `document` is stubbed with the element that holds the focus.
    $script = <<<'JS'
        const [modifierless, withCtrl] = JSON.parse(process.argv[1]);
        const run = (expression, event, active = { tagName: 'BODY', closest: () => null }) =>
            Function('e', 'document', 'return (' + expression + ');')(
                { key: '', repeat: false, ctrlKey: false, metaKey: false, altKey: false, shiftKey: false, ...event },
                { activeElement: active },
            );
        const failures = [];
        const check = (label, actual, expected) => { if (actual !== expected) failures.push(label); };

        check('plain n fires', run(modifierless, { key: 'n' }), true);
        check('Ctrl+N does not fire', run(modifierless, { key: 'n', ctrlKey: true }), false);
        check('Cmd+N does not fire', run(modifierless, { key: 'n', metaKey: true }), false);
        check('Alt+N does not fire', run(modifierless, { key: 'n', altKey: true }), false);
        check('a held key does not fire', run(modifierless, { key: 'n', repeat: true }), false);
        check('typing in an input does not fire', run(modifierless, { key: 'n' }, { tagName: 'INPUT', closest: () => null }), false);
        check('typing in an editor does not fire', run(modifierless, { key: 'n' }, { tagName: 'P', closest: () => ({}) }), false);
        check('an event without key does not throw', run(modifierless, { key: undefined }), false);
        check('Ctrl+K fires', run(withCtrl, { key: 'k', ctrlKey: true }), true);
        check('Cmd+K fires', run(withCtrl, { key: 'k', metaKey: true }), true);
        check('K alone does not fire', run(withCtrl, { key: 'k' }), false);
        check('a held Ctrl+K does not fire', run(withCtrl, { key: 'k', ctrlKey: true, repeat: true }), false);

        if (failures.length) { console.error(failures.join('\n')); process.exit(1); }
    JS;

    [$exitCode, $output] = zzRunNode(['-e', $script, json_encode([$modifierless, $withCtrl])]);

    expect($exitCode)->toBe(0, $output);
});

it('ships the extracted modules in the built asset', function (): void {
    $moduleDir = dirname(__DIR__, 2);
    $manifest = json_decode((string) file_get_contents($moduleDir . '/dist/build/manifest.json'), true);
    $asset = $manifest['resources/js/noerd.js']['file'] ?? null;

    expect($asset)->not->toBeNull();

    $built = (string) file_get_contents($moduleDir . '/dist/build/' . $asset);

    // Strings only the new code carries — a stale dist/ would miss them.
    expect($built)->toContain('aria-invalid')
        ->and($built)->toContain('noerdActionMenu')
        ->and($built)->toContain('data-noerd-action-menu')
        ->and($built)->toContain('https://');

    // Exactly one bundle: a superseded hashed file must not linger next to it.
    expect(glob($moduleDir . '/dist/build/assets/*.js'))->toHaveCount(1);
});
