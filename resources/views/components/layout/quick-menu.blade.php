<?php

use Livewire\Component;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\StaticConfigHelper;

new class extends Component {
    /** @var array{buttons: array<int, array<string, mixed>>} */
    public array $config = ['buttons' => []];

    public function mount(): void
    {
        $configPath = base_path('app-configs/quick-menu.yml');
        $config = file_exists($configPath) ? StaticConfigHelper::parseYamlFile($configPath) : [];

        $this->config = ['buttons' => $config['buttons'] ?? []];
    }

    public function canAccess(string $policy): bool
    {
        return AccessHelper::canPassGate($policy);
    }

    public function showTenantSwitcher(): bool
    {
        $user = NoerdAuth::user();

        return config('noerd.features.multi_tenant')
            && ($user->tenants->count() > 1
                || ($user->isAdmin() && config('noerd.features.new_tenant')));
    }
} ?>

{{-- The tenant switcher stays in a non-overflow row so its dropdown can overlap freely; only the
     YAML buttons scroll horizontally when they get too wide (overflow-x-auto would otherwise clip
     the dropdown vertically as well). --}}
<div class="flex items-center gap-x-2 min-w-0 flex-1">
    @if($this->showTenantSwitcher())
        <div class="shrink-0">
            <livewire:noerd::layout.tenant-switcher />
        </div>
    @endif

    {{-- overflow-x-scroll (not auto) keeps the 6px scrollbar track permanently reserved and the
         -mb-[6px] cancels it out of the layout, so the buttons stay vertically centered and never
         shift when the scrollbar appears — it renders in the topbar's bottom padding instead.
         noerd-scrollbar-idle hides the thumb while nothing overflows (a custom WebKit scrollbar
         would otherwise draw it at full length); the ResizeObserver keeps the class in sync when
         the container or a button changes size. --}}
    <div class="noerd-scrollbar noerd-scrollbar-idle flex items-center gap-x-2 overflow-x-scroll -mb-[6px] min-w-0 flex-1 p-1"
         x-data
         x-init="const sync = () => $el.classList.toggle('noerd-scrollbar-idle', $el.scrollWidth <= $el.clientWidth);
                 sync();
                 const observer = new ResizeObserver(sync);
                 observer.observe($el);
                 Array.from($el.children).forEach((child) => observer.observe(child))">
        {{-- The optional `app:` (string) / `apps:` (list) key ties a button to
             tenant apps: it renders only when at least one of them is assigned
             to the tenant AND the app permission allows it — users a restricted
             app denies must not reach it through the quick-menu. --}}
        @foreach($config['buttons'] ?? [] as $button)
            @php
                $buttonApps = array_merge(
                    isset($button['app']) ? [(string) $button['app']] : [],
                    array_map('strval', (array) ($button['apps'] ?? [])),
                );
            @endphp
            @if((!isset($button['policy']) || $this->canAccess($button['policy']))
                && ($buttonApps === [] || \Noerd\Helpers\AccessHelper::canUseApp(...$buttonApps)))
                <div class="shrink-0">
                    <livewire:dynamic-component :component="$button['component']" :wire:key="'quick-menu-' . $button['component']" />
                </div>
            @endif
        @endforeach
    </div>
</div>
