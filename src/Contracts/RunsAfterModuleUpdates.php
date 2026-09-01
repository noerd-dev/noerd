<?php

declare(strict_types=1);

namespace Noerd\Contracts;

/**
 * Marker for a `noerd:update-{module}` command that `noerd:update-all` must run
 * AFTER every other update — e.g. a module that re-adds entries to the core's
 * published navigation, which a preceding `noerd:update --force` rewrites.
 */
interface RunsAfterModuleUpdates {}
