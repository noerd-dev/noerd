<?php

namespace Noerd\Database\Seeders;

use Illuminate\Database\Seeder;
use Noerd\Models\SetupLanguage;

class SetupLanguageSeeder extends Seeder
{
    public function run(): void
    {
        SetupLanguage::ensureDefaultLanguages();
    }
}
