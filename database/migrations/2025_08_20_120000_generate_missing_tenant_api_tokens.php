<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Iterate tenants with missing or empty api_token and assign a unique token
        DB::table('tenants')
            ->whereNull('api_token')
            ->orWhere('api_token', '=', '')
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($tenants): void {
                foreach ($tenants as $tenant) {
                    DB::table('tenants')
                        ->where('id', $tenant->id)
                        ->update([
                            'api_token' => Str::uuid()->toString(),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank to avoid removing assigned API tokens
    }
};

