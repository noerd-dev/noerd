<?php

namespace Noerd\Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\Tenant;

class CreateTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noerd:create-tenant
                            {--name= : The name of the tenant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new tenant with default profiles';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Get name
        $name = $this->option('name');
        if (empty($name)) {
            $name = text(
                label: 'What is the tenant name?',
                required: true,
                validate: function (string $value) {
                    if (mb_strlen($value) < 3) {
                        return 'Tenant name must be at least 3 characters.';
                    }
                    if (mb_strlen($value) > 50) {
                        return 'Tenant name must be at most 50 characters.';
                    }

                    return null;
                },
            );
        } else {
            // Validate name when passed as option
            if (mb_strlen($name) < 3) {
                $this->error('Tenant name must be at least 3 characters.');

                return self::FAILURE;
            }
            if (mb_strlen($name) > 50) {
                $this->error('Tenant name must be at most 50 characters.');

                return self::FAILURE;
            }
        }

        // Create the tenant
        $tenant = new Tenant();
        $tenant->name = $name;
        $tenant->uuid = Str::uuid()->toString();
        $tenant->save();

        $this->info("Tenant '{$tenant->name}' created successfully.");
        $this->line("  ID: {$tenant->id}");
        $this->line("  UUID: {$tenant->uuid}");

        // Create default USER profile
        Profile::create([
            'tenant_id' => $tenant->id,
            'key' => 'USER',
            'name' => 'User',
        ]);
        $this->info('  ✓ Created USER profile');

        // Create default ADMIN profile
        Profile::create([
            'tenant_id' => $tenant->id,
            'key' => 'ADMIN',
            'name' => 'Admin',
        ]);
        $this->info('  ✓ Created ADMIN profile');

        $this->newLine();
        $this->info("✅ Tenant '{$tenant->name}' is ready to use!");

        return self::SUCCESS;
    }
}
