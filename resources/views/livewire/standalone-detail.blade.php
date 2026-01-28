<?php

use Livewire\Volt\Component;
use Illuminate\Support\Str;
use Noerd\Noerd\Helpers\TenantHelper;

new class extends Component {
    public string $component;
    public string $id;
    public array $queryParams = [];

    public function mount(string $component, string $id): void
    {
        $this->component = $component;
        $this->id = $id;
        $this->queryParams = request()->query();

        // Find the module containing this component
        $moduleName = $this->findComponentModule($component);

        // Validate that the component exists
        if (!$moduleName) {
            abort(404, "Component '{$component}' not found");
        }

        TenantHelper::setSelectedApp(null);
        session(['hideNavigation' => true]);
    }

    private function findComponentModule(string $component): ?string
    {
        // Check if the livewire component view file exists in main resources
        $viewPath = resource_path("views/livewire/{$component}.blade.php");
        if (file_exists($viewPath)) {
            return 'main'; // Main Laravel resources directory
        }

        // Check all modules dynamically
        $modulesPath = base_path('app-modules');
        if (is_dir($modulesPath)) {
            $moduleDirectories = glob($modulesPath . '/*', GLOB_ONLYDIR);

            foreach ($moduleDirectories as $moduleDir) {
                $moduleName = basename($moduleDir);
                $moduleViewPath = base_path("app-modules/{$moduleName}/resources/views/livewire/{$component}.blade.php");

                if (file_exists($moduleViewPath)) {
                    return $moduleName;
                }
            }
        }

        return null;
    }

    private function componentExists(string $component): bool
    {
        return $this->findComponentModule($component) !== null;
    }

    private function getComponentTitle(): string
    {
        // Convert component name to readable title
        $title = str_replace('-detail', '', $this->component);
        return Str::title(str_replace('-', ' ', $title));
    }

    private function getBackUrl(): string
    {
        // Map component to its table view
        $componentToTable = [
            'page-detail' => 'cms.pages',
            'navigation-detail' => 'cms.navigation',
            'collection-detail' => 'cms.collection-files',
            'form-request-detail' => 'cms.form-requests',
            'global-parameter-detail' => 'cms.global-parameters',
        ];

        $route = $componentToTable[$this->component] ?? 'cms.pages';
        return route($route);
    }

    private function getComponentParameters(): array
    {
        // Start with common parameters
        $params = [
            'disableModal' => true,
            'wire:key' => "standalone-{$this->component}-{$this->id}",
        ];

        // Handle special cases for different component types
        switch ($this->component) {
            case 'collection-detail':
                if (isset($this->queryParams['fileName'])) {
                    $params['fileName'] = $this->queryParams['fileName'];
                } else {
                    // For collection components, ID might not be numeric
                    $params['fileName'] = $this->id . '.yml';
                }
                break;

            default:
                // For most components, use modelId
                if (is_numeric($this->id)) {
                    $params['modelId'] = (int)$this->id;
                }
                break;
        }

        return $params;
    }

    private function getEntityName(): string
    {
        // Try to get the entity name from the model for better breadcrumbs
        try {
            switch ($this->component) {
                case 'page-detail':
                    if (is_numeric($this->id)) {
                        $page = \Noerd\Cms\Models\Page::find($this->id);
                        if ($page && $page->name) {
                            $name = is_array($page->name)
                                ? ($page->name[session('selectedLanguage', 'de')] ?? array_values($page->name)[0] ?? '')
                                : $page->name;
                            return $name ?: 'Page';
                        }
                    }
                    break;

                case 'navigation-detail':
                    if (is_numeric($this->id)) {
                        $nav = \Noerd\Cms\Models\Navigation::find($this->id);
                        if ($nav && $nav->name) {
                            $name = is_array($nav->name)
                                ? ($nav->name[session('selectedLanguage', 'de')] ?? array_values($nav->name)[0] ?? '')
                                : $nav->name;
                            return $name ?: 'Navigation';
                        }
                    }
                    break;

                case 'collection-detail':
                    $fileName = $this->queryParams['fileName'] ?? ($this->id . '.yml');
                    return str_replace('.yml', '', $fileName);

                default:
                    return $this->getComponentTitle() . ' #' . $this->id;
            }
        } catch (\Exception $e) {
            // Fallback if model loading fails
        }

        return $this->getComponentTitle() . ' #' . $this->id;
    }
} ?>

<div class="p-8">
    <!-- Breadcrumbs -->
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-4">
                <li>
                    <div>
                        <a href="{{ $this->getBackUrl() }}" class="text-sm font-medium text-gray-500">
                            <span class="sr-only">{{ $this->getComponentTitle() }}</span>
                            {{ Str::plural($this->getComponentTitle()) }}
                        </a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="flex-shrink-0 h-4 w-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20"
                             aria-hidden="true">
                            <path d="M5.555 17.776l8-16 .894.448-8 16-.894-.448z"/>
                        </svg>
                        <span class="ml-4 text-sm font-medium text-gray-500">
                            {{ $this->getEntityName() }}
                        </span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <!-- Dynamic Component -->
    @php
        $componentParams = $this->getComponentParameters();
        $wireKey = $componentParams['wire:key'];
        unset($componentParams['wire:key']); // Remove wire:key from params array
    @endphp

    @livewire($component, $componentParams, key($wireKey))
</div>