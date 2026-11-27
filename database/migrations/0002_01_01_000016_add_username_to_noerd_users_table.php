<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An account signs in with its e-mail address OR its username: both are
 * unique across the installation and an account carries at least one of them
 * (NoerdUser enforces that — a person without a mailbox still gets an
 * account). A username never contains an "@", so the two can never collide.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('noerd_users') || Schema::hasColumn('noerd_users', 'username')) {
            return;
        }

        Schema::table('noerd_users', function (Blueprint $table): void {
            $table->string('username', 64)->nullable()->unique()->after('email');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('noerd_users') || ! Schema::hasColumn('noerd_users', 'username')) {
            return;
        }

        Schema::table('noerd_users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
