<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Noerd\Providers\NoerdModuleServiceProvider;
use Noerd\Services\FieldTypeRegistry;
use Noerd\Services\PicklistRegistry;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\FieldTypeDefinition;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | NoerdModuleServiceProvider derives a module's plumbing from its key and root:
 | views and the Livewire namespace, translations, the route file, the config
 | files, the commands and the three declarative registries. Proven against a
 | module directory written at runtime — never a shipped module.
 */

class ZzModulePingCommand extends Command
{
    protected $signature = 'zzmod:ping';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}

class ZzModuleServiceProvider extends NoerdModuleServiceProvider
{
    public static string $root = '';

    public static bool $registered = false;

    public static bool $booted = false;

    protected string $moduleKey = 'zzmod';

    protected array $commands = [ZzModulePingCommand::class];

    protected array $configs = ['zzmod'];

    protected function registerModule(): void
    {
        self::$registered = true;
    }

    protected function bootModule(): void
    {
        self::$booted = true;
    }

    protected function relationFields(): array
    {
        return ['zzModThingRelation' => RelationFieldDefinition::model('zzmod::things-list')];
    }

    protected function fieldTypes(): array
    {
        return ['zzModColor' => FieldTypeDefinition::include('zzmod::components.forms.color')];
    }

    protected function picklists(): array
    {
        return ['zzModStates' => fn(): array => ['a' => 'A']];
    }

    protected function moduleRoot(): string
    {
        return self::$root;
    }
}

beforeEach(function (): void {
    $root = storage_path('framework/testing/zz-module-' . uniqid());
    ZzModuleServiceProvider::$root = $root;
    ZzModuleServiceProvider::$registered = false;
    ZzModuleServiceProvider::$booted = false;

    File::ensureDirectoryExists($root . '/resources/views/components');
    File::ensureDirectoryExists($root . '/resources/lang');
    File::ensureDirectoryExists($root . '/routes');
    File::ensureDirectoryExists($root . '/config');

    File::put($root . '/resources/views/components/zz-thing.blade.php', implode("\n", [
        '<?php',
        'use Livewire\Component;',
        'new class extends Component {',
        '    public function render(): string { return \'<div>Zz Thing rendered</div>\'; }',
        '}; ?>',
    ]));
    File::put($root . '/resources/views/plain.blade.php', 'plain view');
    File::put($root . '/resources/lang/de.json', json_encode(['Zz Hello' => 'Zz Hallo']));
    File::put($root . '/routes/zzmod-routes.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('zzmod/thing', fn () => 'ok')->name('zzmod.thing');\n");
    File::put($root . '/config/zzmod.php', "<?php\n\nreturn ['flag' => 'merged'];\n");

    // The application is booted already: register() runs and boot() follows at once.
    $this->app->register(new ZzModuleServiceProvider($this->app));

    // Routes registered after the application booted are only found by name
    // once the lookup table is rebuilt — in a real boot the router does this itself.
    Route::getRoutes()->refreshNameLookups();
});

afterEach(function (): void {
    File::deleteDirectory(ZzModuleServiceProvider::$root);
});

it('wires views, the Livewire namespace, translations, routes and the config from the module key', function (): void {
    expect(View::exists('zzmod::plain'))->toBeTrue()
        ->and(Route::has('zzmod.thing'))->toBeTrue()
        ->and(config('zzmod.flag'))->toBe('merged')
        ->and(ZzModuleServiceProvider::$registered)->toBeTrue()
        ->and(ZzModuleServiceProvider::$booted)->toBeTrue();

    app()->setLocale('de');
    expect(__('Zz Hello'))->toBe('Zz Hallo');

    Livewire::test('zzmod::zz-thing')->assertSee('Zz Thing rendered');
});

it('registers the commands and the declarative registries', function (): void {
    expect(array_keys(app(Kernel::class)->all()))->toContain('zzmod:ping')
        ->and(app(RelationFieldRegistry::class)->has('zzModThingRelation'))->toBeTrue()
        ->and(app(FieldTypeRegistry::class)->has('zzModColor'))->toBeTrue()
        ->and(app(PicklistRegistry::class)->has('zzModStates'))->toBeTrue();
});

it('refuses a provider without a module key', function (): void {
    $provider = new class ($this->app) extends NoerdModuleServiceProvider {};

    expect(fn() => $provider->boot())->toThrow(LogicException::class, 'must declare its $moduleKey');
});
