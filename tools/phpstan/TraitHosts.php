<?php

declare(strict_types=1);

namespace Noerd\Tools\PHPStan;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Noerd\Commands\Concerns\HasModuleInstallation;
use Noerd\Commands\Concerns\PublishesAuditMigration;
use Noerd\Traits\AdministersNoerdUsers;
use Noerd\Traits\GuardedByObjectPermission;
use Noerd\Traits\HasEmailPreview;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdList;
use Noerd\Traits\NoerdPage;
use Noerd\Traits\NoerdPositionRow;
use Noerd\Traits\NoerdSettingsPage;
use Noerd\Traits\SetupLanguageFilter;

/*
 | PHPStan analyses a trait only through a class that uses it, and noerd's
 | traits are used by Blade single-file components and module classes PHPStan
 | never sees. These hosts give every trait one analysed user. Development
 | only — not autoloaded, not shipped (.gitattributes).
 */

final class ListHost extends Component
{
    use NoerdList;
    use SetupLanguageFilter;
}

final class PageHost extends Component
{
    use NoerdPage;
}

final class DetailHost extends Component
{
    use AdministersNoerdUsers;
    use NoerdDetail;
}

final class SettingsPageHost extends Component
{
    use NoerdSettingsPage;
}

final class PositionRowHost extends Component
{
    use NoerdPositionRow;
}

final class EmailPreviewHost extends Component
{
    use HasEmailPreview;

    public function getSampleEmailData(): array
    {
        return [];
    }

    protected function getEmailData(): array
    {
        return [];
    }

    protected function getEmailViewName(): string
    {
        return '';
    }

    protected function getEmailRateLimitPrefix(): string
    {
        return '';
    }
}

final class GuardedModelHost extends Model
{
    use GuardedByObjectPermission;
}

final class ModuleInstallCommandHost extends Command
{
    use HasModuleInstallation;
    use PublishesAuditMigration;

    protected function getModuleName(): string
    {
        return '';
    }

    protected function getModuleKey(): string
    {
        return '';
    }

    protected function getDefaultAppTitle(): string
    {
        return '';
    }

    protected function getAppIcon(): string
    {
        return '';
    }

    protected function getAppRoute(): string
    {
        return '';
    }

    protected function getSourceDir(): string
    {
        return '';
    }

    protected function getModuleRoot(): string
    {
        return '';
    }
}
