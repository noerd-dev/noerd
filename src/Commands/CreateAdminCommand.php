<?php

namespace Noerd\Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

use Noerd\Noerd\Models\User;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noerd:create-admin
                            {--name= : The name of the user}
                            {--email= : The email of the user}
                            {--password= : The password of the user}
                            {--super-admin : Mark this user as super admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user and make them an admin';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Get name
        $name = $this->option('name');
        if (empty($name)) {
            $name = text(
                label: 'What is the user\'s name?',
                required: true,
            );
        }

        // Get email
        $email = $this->option('email');
        if (empty($email)) {
            $email = text(
                label: 'What is the user\'s email?',
                required: true,
                validate: function (string $value) {
                    if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return 'Please enter a valid email address.';
                    }
                    if (User::where('email', $value)->exists()) {
                        return 'A user with this email already exists.';
                    }

                    return null;
                },
            );
        } else {
            // Validate email when passed as option
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error('Please enter a valid email address.');

                return self::FAILURE;
            }
            if (User::where('email', $email)->exists()) {
                $this->error('A user with this email already exists.');

                return self::FAILURE;
            }
        }

        // Get password
        $passwordValue = $this->option('password');
        if (empty($passwordValue)) {
            $passwordValue = password(
                label: 'Enter a password (minimum 8 characters)',
                required: true,
                validate: function (string $value) {
                    if (mb_strlen($value) < 8) {
                        return 'Password must be at least 8 characters.';
                    }

                    return null;
                },
            );
        } else {
            // Validate password when passed as option
            if (mb_strlen($passwordValue) < 8) {
                $this->error('Password must be at least 8 characters.');

                return self::FAILURE;
            }
        }

        // Create the user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($passwordValue),
        ]);

        // Set super admin if requested
        if ($this->option('super-admin')) {
            $user->super_admin = true;
            $user->save();
            $this->info("User '{$user->name}' created as Super Admin.");
        } else {
            $this->info("User '{$user->name}' created successfully.");
        }

        // Make the user admin using existing command
        $this->call('noerd:make-admin', ['user_id' => $user->id]);

        return self::SUCCESS;
    }
}
