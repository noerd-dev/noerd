<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Noerd\Commands\NoerdInstallCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(Tests\TestCase::class);

describe('NoerdInstallCommand updateViteConfig', function (): void {
    beforeEach(function (): void {
        $this->viteConfigPath = base_path('vite.config.js');
        $this->originalContent = file_get_contents($this->viteConfigPath);
    });

    afterEach(function (): void {
        file_put_contents($this->viteConfigPath, $this->originalContent);
    });

    it('adds noerd entry point when missing from vite.config.js', function (): void {
        $contentWithoutNoerd = str_replace(
            "                'app-modules/noerd/resources/js/noerd.js',\n",
            '',
            $this->originalContent,
        );
        file_put_contents($this->viteConfigPath, $contentWithoutNoerd);

        expect(str_contains($contentWithoutNoerd, 'app-modules/noerd/resources/js/noerd.js'))->toBeFalse();

        $command = new NoerdInstallCommand();
        $command->setLaravel(app());
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

        $method = new ReflectionMethod($command, 'updateViteConfig');
        $method->invoke($command);

        $updatedContent = file_get_contents($this->viteConfigPath);
        expect(str_contains($updatedContent, 'app-modules/noerd/resources/js/noerd.js'))->toBeTrue();
    });

    it('does not duplicate noerd entry point when already present', function (): void {
        expect(str_contains($this->originalContent, 'app-modules/noerd/resources/js/noerd.js'))->toBeTrue();

        $command = new NoerdInstallCommand();
        $command->setLaravel(app());
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

        $method = new ReflectionMethod($command, 'updateViteConfig');
        $method->invoke($command);

        $updatedContent = file_get_contents($this->viteConfigPath);
        $count = substr_count($updatedContent, 'app-modules/noerd/resources/js/noerd.js');
        expect($count)->toBe(1);
    });
});
