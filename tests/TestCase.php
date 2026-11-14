<?php

declare(strict_types=1);

namespace Noerd\Tests;

use Livewire\Livewire;
use Noerd\Testing\TestCase as NoerdTestCase;

/**
 * The base of the package's OWN suite: the shipped Noerd\Testing\TestCase plus
 * the `noerd-test::` Livewire namespace of the fixture components (synthetic
 * layouts, theme probes) under tests/Feature/fixtures/components. Those
 * fixtures are not shipped (tests/ is export-ignored), so the namespace is
 * registered here and never by the test case modules build on.
 */
abstract class TestCase extends NoerdTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Livewire::addNamespace('noerd-test', viewPath: __DIR__ . '/Feature/fixtures/components');
    }
}
