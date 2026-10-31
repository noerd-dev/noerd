<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/*
 | Architecture guard: every module the code uses is declared in composer.json,
 | and nothing the module must never reference appears anywhere in it.
 | Allowances (with their reason) and forbidden namespaces are declared in
 | composer.json under extra.noerd.boundary — never in this file.
 */

it('keeps its module boundary', function (): void {
    assertModuleBoundary(dirname(__DIR__, 2));
});
