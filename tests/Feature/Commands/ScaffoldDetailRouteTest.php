<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Noerd\Commands\Concerns\GeneratesResourceFiles;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/**
 * The scaffolder declares BOTH properties on a generated list: the route opens the
 * record and rewrites the URL, the component stays as the fallback.
 */
class ZzScaffoldProbe extends Command
{
    use GeneratesResourceFiles;

    public function __construct()
    {
        parent::__construct();

        $this->filesystem = new Filesystem();
        $this->entity = 'gadget';
        $this->entities = 'gadgets';
        $this->appConfigName = 'workshop';
    }

    public function setDetailRouteName(?string $name): void
    {
        $this->detailRouteName = $name;
    }

    public function annotate(string $path): void
    {
        $this->annotateListDetailRoute($path);
    }

    public function line($string, $style = null, $verbosity = null): void
    {
        // Silence console output in tests.
    }
}

function writeGeneratedList(string $body = "    public \$detailComponent = 'gadget-detail';\n"): string
{
    $path = sys_get_temp_dir() . '/zz-gadgets-list-' . uniqid() . '.blade.php';
    file_put_contents($path, "<?php\n\nnew class extends Component {\n    use NoerdList;\n\n" . $body . "};\n");

    return $path;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir() . '/zz-gadgets-list-*.blade.php') ?: [] as $file) {
        @unlink($file);
    }
});

it('declares the detail route next to the detail component on a generated list', function (): void {
    $path = writeGeneratedList();

    $probe = new ZzScaffoldProbe();
    $probe->setDetailRouteName('workshop.gadget.detail');
    $probe->annotate($path);

    expect(file_get_contents($path))
        ->toContain("public ?string \$detailRoute = 'workshop.gadget.detail';")
        ->toContain("public \$detailComponent = 'gadget-detail';");
});

it('leaves the generated list untouched when no detail route was created', function (): void {
    $path = writeGeneratedList();
    $before = file_get_contents($path);

    $probe = new ZzScaffoldProbe();
    $probe->setDetailRouteName(null);
    $probe->annotate($path);

    expect(file_get_contents($path))->toBe($before);
});

it('does not declare the detail route twice', function (): void {
    $path = writeGeneratedList(
        "    public ?string \$detailRoute = 'workshop.gadget.detail';\n\n    public \$detailComponent = 'gadget-detail';\n",
    );
    $before = file_get_contents($path);

    $probe = new ZzScaffoldProbe();
    $probe->setDetailRouteName('workshop.gadget.detail');
    $probe->annotate($path);

    expect(file_get_contents($path))->toBe($before);
});
