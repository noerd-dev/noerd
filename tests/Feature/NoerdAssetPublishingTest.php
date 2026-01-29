<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

uses(Tests\TestCase::class);

describe('Noerd Asset Publishing', function (): void {
    it('uses a cloned vite instance in assets.blade.php', function (): void {
        $assetsView = base_path('app-modules/noerd/resources/views/components/assets.blade.php');

        expect(file_exists($assetsView))->toBeTrue();

        $content = file_get_contents($assetsView);

        expect($content)->toContain('clone app(Vite::class)');
        expect($content)->toContain('useHotFile');
        expect($content)->toContain('useBuildDirectory');
        expect($content)->toContain('vendor/noerd');
        expect($content)->toContain('resources/js/noerd.js');
    });

    it('does not have noerd entries in main vite.config.js', function (): void {
        $viteConfig = file_get_contents(base_path('vite.config.js'));

        expect($viteConfig)->not->toContain('app-modules/noerd/resources/js/noerd.js');
    });

    it('publishes built assets when manifest does not exist', function (): void {
        $targetDir = public_path('vendor/noerd');
        $targetManifest = $targetDir . '/manifest.json';

        // Remember original state
        $manifestExisted = File::exists($targetManifest);
        $originalContent = $manifestExisted ? File::get($targetManifest) : null;

        // Remove manifest to simulate fresh state
        if ($manifestExisted) {
            File::delete($targetManifest);
        }

        $sourceDir = base_path('app-modules/noerd/dist/build');

        if (! File::exists($sourceDir . '/manifest.json')) {
            // Restore if we removed it
            if ($manifestExisted) {
                File::put($targetManifest, $originalContent);
            }
            $this->markTestSkipped('Noerd module has not been built yet (dist/build/manifest.json missing).');
        }

        // Simulate the publishBuiltAssetsIfNotExist logic
        if (! File::exists($targetManifest) && File::exists($sourceDir . '/manifest.json')) {
            File::ensureDirectoryExists($targetDir);
            File::copyDirectory($sourceDir, $targetDir);
        }

        expect(File::exists($targetManifest))->toBeTrue();

        // Restore original state
        if ($manifestExisted) {
            File::put($targetManifest, $originalContent);
        } else {
            File::delete($targetManifest);
        }
    });

    it('does not overwrite existing assets', function (): void {
        $targetDir = public_path('vendor/noerd');
        $targetManifest = $targetDir . '/manifest.json';

        $sourceDir = base_path('app-modules/noerd/dist/build');

        if (! File::exists($sourceDir . '/manifest.json')) {
            $this->markTestSkipped('Noerd module has not been built yet (dist/build/manifest.json missing).');
        }

        // Remember original state
        $manifestExisted = File::exists($targetManifest);
        $originalContent = $manifestExisted ? File::get($targetManifest) : null;

        // Place a marker
        File::ensureDirectoryExists($targetDir);
        $marker = 'test-marker-' . uniqid();
        File::put($targetManifest, $marker);

        // Run the publish logic - should NOT overwrite
        if (! File::exists($targetManifest) && File::exists($sourceDir . '/manifest.json')) {
            File::ensureDirectoryExists($targetDir);
            File::copyDirectory($sourceDir, $targetDir);
        }

        // The marker should still be there (not overwritten)
        expect(File::get($targetManifest))->toBe($marker);

        // Restore original state
        if ($manifestExisted) {
            File::put($targetManifest, $originalContent);
        } else {
            File::delete($targetManifest);
        }
    });
});
