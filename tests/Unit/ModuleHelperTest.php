<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Noerd\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

uses(TestCase::class);

/**
 * Self-tests for the two module helpers in tests/helpers.php. They are the
 * standard proofs every module suite relies on, so both halves are pinned here:
 * the pass path AND the failure they must report. Everything runs against a
 * throwaway `zz-module` skeleton written under the testbench base path.
 */

/**
 * A minimal update command whose publish behaviour the test controls: it copies
 * the fixture module's app-configs into the project, or (when disabled)
 * publishes nothing while still exiting cleanly.
 */
final class ZzUpdateModuleCommand extends Command
{
    public static string $source = '';

    public static string $target = '';

    public static bool $publishes = true;

    protected $signature = 'noerd:update-zz-module {--force : Overwrite existing files without asking}';

    protected $description = 'Publish the zz-module configs (test fixture)';

    public function handle(): int
    {
        if (self::$publishes) {
            File::copyDirectory(self::$source, self::$target);
        }

        return self::SUCCESS;
    }
}

function zzModuleRoot(): string
{
    return base_path('zz-modules');
}

function zzModuleDir(): string
{
    return zzModuleRoot() . '/zz-module';
}

/**
 * Write the throwaway module tree. $require goes into the module's composer.json.
 *
 * @param  array<string, string>  $require
 */
function zzWriteModuleSkeleton(array $require = []): void
{
    $dir = zzModuleDir();

    File::ensureDirectoryExists($dir . '/src');
    File::ensureDirectoryExists($dir . '/app-configs/zz-module/lists');

    File::put($dir . '/composer.json', (string) json_encode([
        'name' => 'zz/module',
        'require' => $require,
        'autoload' => ['psr-4' => ['Zz\\Module\\' => 'src/']],
    ], JSON_PRETTY_PRINT));

    File::put($dir . '/src/ZzThing.php', "<?php\n\nnamespace Zz\\Module;\n\nclass ZzThing {}\n");
    File::put($dir . '/app-configs/zz-module/lists/x.yml', "title: Zz Things\ncolumns:\n  - field: name\n    label: Name\n");

    // A sibling module, so the guard has a foreign namespace to recognise.
    File::ensureDirectoryExists(zzModuleRoot() . '/zz-other');
    File::put(zzModuleRoot() . '/zz-other/composer.json', (string) json_encode([
        'name' => 'zz/other',
        'autoload' => ['psr-4' => ['Zz\\Other\\' => 'src/']],
    ], JSON_PRETTY_PRINT));
}

beforeEach(function (): void {
    File::deleteDirectory(zzModuleRoot());
    File::deleteDirectory(base_path('app-configs/zz-module'));

    ZzUpdateModuleCommand::$source = zzModuleDir() . '/app-configs/zz-module';
    ZzUpdateModuleCommand::$target = base_path('app-configs/zz-module');
    ZzUpdateModuleCommand::$publishes = true;

    app(Kernel::class)->registerCommand(new ZzUpdateModuleCommand());
});

afterEach(function (): void {
    File::deleteDirectory(zzModuleRoot());
    File::deleteDirectory(base_path('app-configs/zz-module'));
});

describe('assertModuleDependenciesDeclared()', function (): void {
    it('passes for a module that references no foreign namespace', function (): void {
        zzWriteModuleSkeleton();

        assertModuleDependenciesDeclared(zzModuleDir());
    });

    it('fails when a foreign module namespace is used without a require entry', function (): void {
        zzWriteModuleSkeleton();
        File::put(zzModuleDir() . '/src/ZzUsesOther.php', "<?php\n\nuse Zz\\Other\\Gadget;\n");

        // The report is a JSON payload, so the package name appears slash-escaped.
        expect(fn(): mixed => assertModuleDependenciesDeclared(zzModuleDir()))
            ->toThrow(AssertionFailedError::class, 'Undeclared module dependencies')
            ->and(fn(): mixed => assertModuleDependenciesDeclared(zzModuleDir()))
            ->toThrow(AssertionFailedError::class, 'zz\/other');
    });

    it('passes once the dependency is declared in composer.json', function (): void {
        zzWriteModuleSkeleton(['zz/other' => '^1.0']);
        File::put(zzModuleDir() . '/src/ZzUsesOther.php', "<?php\n\nuse Zz\\Other\\Gadget;\n");

        assertModuleDependenciesDeclared(zzModuleDir());
    });

    it('accepts an undeclared package that is passed as an explicit allowance', function (): void {
        zzWriteModuleSkeleton();
        File::put(zzModuleDir() . '/src/ZzUsesOther.php', "<?php\n\nuse Zz\\Other\\Gadget;\n");

        assertModuleDependenciesDeclared(zzModuleDir(), ['zz/other']);
    });

    it('also catches a leak in the module tests and in a YAML config', function (string $relative): void {
        zzWriteModuleSkeleton();
        File::ensureDirectoryExists(dirname(zzModuleDir() . '/' . $relative));
        File::put(zzModuleDir() . '/' . $relative, "Zz\\Other\\Gadget\n");

        expect(fn(): mixed => assertModuleDependenciesDeclared(zzModuleDir()))
            ->toThrow(AssertionFailedError::class, 'Undeclared module dependencies');
    })->with([
        'tests/ZzLeakTest.php',
        'app-configs/zz-module/lists/leak.yml',
    ]);
});

describe('assertModuleUpdateCommandPublishesConfigs()', function (): void {
    it('passes for a command that publishes the shipped configs', function (): void {
        zzWriteModuleSkeleton();

        assertModuleUpdateCommandPublishesConfigs('noerd:update-zz-module', zzModuleDir(), 'zz-module');
    });

    it('fails when the command publishes nothing', function (): void {
        zzWriteModuleSkeleton();
        ZzUpdateModuleCommand::$publishes = false;

        expect(fn(): mixed => assertModuleUpdateCommandPublishesConfigs('noerd:update-zz-module', zzModuleDir(), 'zz-module'))
            ->toThrow(AssertionFailedError::class, 'update did not publish');
    });

    it('fails when the module ships no app-configs directory for the key', function (): void {
        zzWriteModuleSkeleton();
        File::deleteDirectory(zzModuleDir() . '/app-configs/zz-module');

        expect(fn(): mixed => assertModuleUpdateCommandPublishesConfigs('noerd:update-zz-module', zzModuleDir(), 'zz-module'))
            ->toThrow(AssertionFailedError::class);
    });

    it('restores a pre-existing installed config directory', function (): void {
        zzWriteModuleSkeleton();

        $target = base_path('app-configs/zz-module');
        File::ensureDirectoryExists($target);
        File::put($target . '/zz-installed.yml', "title: Zz Installed\n");

        assertModuleUpdateCommandPublishesConfigs('noerd:update-zz-module', zzModuleDir(), 'zz-module');

        expect(File::exists($target . '/zz-installed.yml'))->toBeTrue()
            ->and(File::get($target . '/zz-installed.yml'))->toBe("title: Zz Installed\n");
    });

    it('leaves nothing behind when the module was not installed before', function (): void {
        zzWriteModuleSkeleton();

        assertModuleUpdateCommandPublishesConfigs('noerd:update-zz-module', zzModuleDir(), 'zz-module');

        expect(File::isDirectory(base_path('app-configs/zz-module')))->toBeFalse();
    });
});
