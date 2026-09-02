<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Noerd\Models\NoerdUser;

/**
 * Grants or revokes the installation-level super admin flag. The flag is
 * deliberately NOT editable from any screen ($guarded on the model): a tenant
 * admin must never be able to promote an account to administer the whole
 * installation, so the console is the only way in — and, with --revoke, the
 * only way out.
 */
class SuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noerd:super-admin
                            {user : The ID or email of the user}
                            {--revoke : Withdraw the super admin flag instead of granting it}
                            {--force : Revoke even when the user is the last super admin of the installation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant or revoke the installation-wide super admin flag of a user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = $this->resolveUser((string) $this->argument('user'));

        if (! $user) {
            $this->error("User '{$this->argument('user')}' not found.");

            return self::FAILURE;
        }

        return $this->option('revoke') ? $this->revoke($user) : $this->grant($user);
    }

    private function grant(NoerdUser $user): int
    {
        if ($user->isSuperAdmin()) {
            $this->warn("User '{$user->name}' ({$user->email}) already is a super admin.");

            return self::SUCCESS;
        }

        $user->super_admin = true;
        $user->save();

        $this->info("User '{$user->name}' ({$user->email}) is now a super admin.");

        return self::SUCCESS;
    }

    private function revoke(NoerdUser $user): int
    {
        if (! $user->isSuperAdmin()) {
            $this->warn("User '{$user->name}' ({$user->email}) is not a super admin.");

            return self::SUCCESS;
        }

        // Without any super admin nobody administers the installation as a
        // whole any more (unassigned accounts, tenants without an admin).
        // noerd:install can bootstrap one again, but that is a deliberate step.
        $isLast = NoerdUser::query()->where('super_admin', true)->whereKeyNot($user->id)->doesntExist();

        if ($isLast && ! $this->option('force')) {
            $this->error("User '{$user->name}' ({$user->email}) is the last super admin of this installation. Pass --force to revoke anyway.");

            return self::FAILURE;
        }

        $user->super_admin = false;
        $user->save();

        $this->info("User '{$user->name}' ({$user->email}) is no longer a super admin.");

        return self::SUCCESS;
    }

    private function resolveUser(string $identifier): ?NoerdUser
    {
        if (is_numeric($identifier)) {
            return NoerdUser::find((int) $identifier);
        }

        return NoerdUser::query()->where('email', $identifier)->first();
    }
}
