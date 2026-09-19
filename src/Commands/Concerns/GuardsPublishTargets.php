<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use Illuminate\Support\ServiceProvider;

/**
 * The guard every `vendor:publish` call of an install/update command runs first.
 */
trait GuardsPublishTargets
{
    /**
     * Whether `vendor:publish --tag={$tag}` would write into the installation
     * this command is installing into.
     *
     * vendor:publish resolves its targets from the paths the service providers
     * registered when they BOOTED — against the base path the application had
     * then. A command running with a moved base path (a test fixture, a build
     * tool) would therefore overwrite the real installation's files instead of
     * the ones it is publishing into, and `--force` makes that silent. So a
     * publish only runs while its targets still lie inside base_path().
     */
    protected function publishTargetsCurrentInstallation(string $tag, ?string $provider = null): bool
    {
        $paths = ServiceProvider::pathsToPublish($provider, $tag);

        if ($paths === []) {
            return false;
        }

        $base = mb_rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        foreach ($paths as $target) {
            if (! str_starts_with((string) $target, $base)) {
                return false;
            }
        }

        return true;
    }
}
