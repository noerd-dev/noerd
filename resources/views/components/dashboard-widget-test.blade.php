<?php

use Livewire\Component;

/**
 * Test-only widget used by DashboardWidgetsTest to exercise the dashboard-widgets
 * renderer and the x-noerd::dashboard-widget shell with a synthetic component.
 */
new class extends Component {
}; ?>

<x-noerd::dashboard-widget :title="__('Test Widget')" :count="3">
    <div class="px-4 py-2 text-sm">Test Widget Body</div>
</x-noerd::dashboard-widget>
