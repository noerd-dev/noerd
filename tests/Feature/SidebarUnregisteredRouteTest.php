<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * The sidebar must skip navigation entries whose primary `route:` is not
 * registered (e.g. a stale entry left behind by an uninstalled module) instead
 * of letting an unguarded route() call take the whole page down. Uses a
 * synthetic navigation fixture — never asserts shipped configuration.
 */
beforeEach(function (): void {
    $this->navigationPath = base_path('app-configs/setup/navigation.yml');
    $this->navigationBackup = File::exists($this->navigationPath) ? File::get($this->navigationPath) : null;
});

afterEach(function (): void {
    if ($this->navigationBackup !== null) {
        File::put($this->navigationPath, $this->navigationBackup);
    } else {
        File::delete($this->navigationPath);
    }
});

it('renders the page and hides entries whose route is not registered', function (): void {
    File::ensureDirectoryExists(dirname($this->navigationPath));
    File::put($this->navigationPath, <<<'YAML'
-
  title: Setup
  name: setup
  hidden: true
  route: noerd.setup
  block_menus:
    -
      title: Administration
      navigations:
        -
          title: 'Zz Valid Entry'
          route: noerd.users
          heroicon: users
        -
          title: 'Zz Stale Entry'
          route: zz-not-registered-route
          heroicon: users
        -
          title: 'Zz Link Entry'
          link: /zz-somewhere
        -
          title: 'Zz Modal Entry'
          component: zz-some-modal
YAML);

    $admin = NoerdUser::factory()->adminUser()->create();

    $this->actingAs($admin)->get('/setup')
        ->assertOk()
        ->assertSee('Zz Valid Entry')
        ->assertDontSee('Zz Stale Entry')
        ->assertSee('Zz Link Entry')
        ->assertSee('Zz Modal Entry');
});
