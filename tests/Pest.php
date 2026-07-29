<?php

declare(strict_types=1);

/*
 | This file is loaded ONLY when Pest runs from the package root (Pest loads a
 | single Pest.php at <rootPath>/tests). When the suite runs from the host
 | application root (vendor/bin/pest app-modules/noerd/tests), the host's
 | tests/Pest.php is loaded instead and this file is skipped entirely.
 |
 | That is why every test file binds Noerd\Tests\TestCase itself via uses() —
 | do not move those bindings here, or host-root runs would lose them.
 */
