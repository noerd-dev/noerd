<?php

namespace Noerd\Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\Tenant;

use function Laravel\Prompts\text;

class CreateTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noerd:create-tenant
                            {--name= : The name of the tenant}
                            {--from-email= : The from email address for the tenant}
                            {--reply-email= : The reply email address for the tenant}';

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

        // Get optional email addresses
        $fromEmail = $this->option('from-email');
        $replyEmail = $this->option('reply-email');

        // Create the tenant
        $tenant = new Tenant;
        $tenant->name = $name;
        $tenant->hash = Str::uuid()->toString();
        $tenant->api_token = Str::uuid()->toString();

        if ($fromEmail) {
            $tenant->from_email = $fromEmail;
        }
        if ($replyEmail) {
            $tenant->reply_email = $replyEmail;
        }

        $tenant->save();

        $this->info("Tenant '{$tenant->name}' created successfully.");
        $this->line("  ID: {$tenant->id}");
        $this->line("  Hash: {$tenant->hash}");

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
