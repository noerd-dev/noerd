<?php

namespace Noerd\Database\Seeders;

use Illuminate\Database\Seeder;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;

class SetupLanguageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Tenant::all() as $tenant) {
            SetupLanguage::ensureDefaultLanguagesForTenant($tenant->id);
        }
    }
}
