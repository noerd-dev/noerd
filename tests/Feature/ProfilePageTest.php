<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Noerd\Commands\Concerns\WritesHostAppConfigs;
use Noerd\Helpers\TenantHelper;
use Noerd\Middleware\ProfileMiddleware;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Yaml\Yaml;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->profileNavigation = base_path('app-configs/profile/navigation.yml');
    $this->previousProfileNavigation = File::exists($this->profileNavigation) ? File::get($this->profileNavigation) : null;
});

afterEach(function (): void {
    $this->previousProfileNavigation === null
        ? File::delete($this->profileNavigation)
        : File::put($this->profileNavigation, $this->previousProfileNavigation);
});

/**
 * A runtime-written profile navigation — never the shipped one.
 *
 * @param  list<array{title: string, route: string}>  $entries
 */
function zzProfileNavigation(string $path, array $entries): void
{
    File::ensureDirectoryExists(dirname($path));
    File::put($path, Yaml::dump([[
        'title' => 'Profile',
        'name' => 'profile',
        'hidden' => true,
        'route' => 'noerd.profile',
        'block_menus' => [['title' => 'Zz Account', 'navigations' => $entries]],
    ]], 10, 2));
}

it('renders each profile page as a Livewire page with its forms inside the shared section chrome', function (string $route, string $page, string $title, array $forms): void {
    $user = NoerdUser::factory()->withExampleTenant()->create();

    $response = $this->actingAs($user)->get(route($route));

    $response->assertOk()
        ->assertSeeLivewire($page)
        ->assertSee($title)
        // <x-noerd::profile-layout> renders the <x-noerd::page> chrome.
        ->assertSee('data-noerd-page', false);

    foreach ($forms as $form) {
        $response->assertSeeLivewire($form);
    }

    // The title belongs to the component, not to the route: opened anywhere else
    // (the modal stack mounts it without the route) it still carries its header.
    Livewire::test($page)->assertOk()->assertSee($title);

    // <x-noerd::profile-section> keeps the box chrome and the reading width in
    // one place — every form must land inside both.
    expect(mb_substr_count($response->getContent(), 'max-w-xl'))->toBeGreaterThanOrEqual(count($forms));
})->with([
    'profile and password' => ['noerd.profile', 'noerd::profile-page', 'Profile and Password', ['noerd::profile.update-profile-information-form', 'noerd::profile.update-password-form']],
    'language and locale' => ['noerd.profile.language', 'noerd::profile-language-page', 'Language and Locale', ['noerd::profile.update-language-form', 'noerd::profile.update-locale-form']],
    'tenant access' => ['noerd.profile.tenants', 'noerd::profile-tenants-page', 'Tenant Access', ['noerd::profile.tenant-access-display-form']],
    'delete account' => ['noerd.profile.delete', 'noerd::profile-delete-page', 'Delete Account', ['noerd::profile.delete-user-form']],
]);

it('shows the profile navigation of app-configs/profile in the sidebar of a profile page', function (): void {
    zzProfileNavigation($this->profileNavigation, [
        ['title' => 'Zz Profile Entry', 'route' => 'noerd.profile.language'],
        ['title' => 'Zz Entry Of A Missing Module', 'route' => 'zz.not-registered'],
    ]);
    $user = NoerdUser::factory()->withExampleTenant()->create();

    $this->actingAs($user)->get(route('noerd.profile'))
        ->assertOk()
        ->assertSee(['Zz Account', 'Zz Profile Entry'])
        ->assertDontSee('Zz Entry Of A Missing Module');

    expect(TenantHelper::getSelectedApp())->toBe(ProfileMiddleware::APP);
});

it('lets a module add its entry to the profile navigation, once', function (): void {
    zzProfileNavigation($this->profileNavigation, [['title' => 'Zz Profile Entry', 'route' => 'noerd.profile']]);

    $command = new class extends Command {
        use WritesHostAppConfigs;

        public function addEntry(): void
        {
            $this->ensureProfileNavigation('Zz Account', ['title' => 'Zz Connectors', 'route' => 'zz.connectors', 'heroicon' => 'link']);
        }
    };
    $command->setLaravel(app());
    $command->setOutput(new \Illuminate\Console\OutputStyle(new ArrayInput([]), new BufferedOutput()));

    $command->addEntry();
    $command->addEntry();

    $navigations = Yaml::parseFile($this->profileNavigation)[0]['block_menus'][0]['navigations'];

    expect(array_column($navigations, 'route'))->toBe(['noerd.profile', 'zz.connectors']);
});
