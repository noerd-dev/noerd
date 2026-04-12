<section class="mb-12">
    <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-300 pb-2 mb-4">{{ __('ui_library_section_layout') }}</h2>

    {{-- Info Box --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Info Box</h3>
    <p class="text-sm text-gray-500 mb-3">Blue informational alert box for helpful messages.</p>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <x-noerd::info-box>This is an informational message. Use it to provide helpful context to the user.</x-noerd::info-box>
    </div>
    <details class="group mb-6">
        <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 py-1">Show code</summary>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code>&lt;x-noerd::info-box&gt;This is an informational message.&lt;/x-noerd::info-box&gt;</code></pre>
    </details>

    {{-- Box --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Box</h3>
    <p class="text-sm text-gray-500 mb-3">Container with brand navigation background color.</p>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <x-noerd::box>
            <p class="text-white text-sm">Content inside a box with brand-navi background.</p>
        </x-noerd::box>
    </div>
    <details class="group mb-6">
        <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 py-1">Show code</summary>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code>&lt;x-noerd::box&gt;
    &lt;p class="text-white"&gt;Content here&lt;/p&gt;
&lt;/x-noerd::box&gt;</code></pre>
    </details>

    {{-- Dashboard Card --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Dashboard Card</h3>
    <p class="text-sm text-gray-500 mb-3">Card for dashboard displays with icon, title, and value. Can link to external URLs or open modals.</p>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <div class="flex flex-wrap">
            <x-noerd::dashboard-card heroicon="users" title="Customers" :value="128" />
            <x-noerd::dashboard-card heroicon="document-text" title="Invoices" :value="42" />
            <x-noerd::dashboard-card heroicon="currency-euro" title="Revenue" :value="98500" />
        </div>
    </div>
    <details class="group mb-6">
        <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 py-1">Show code</summary>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code>&lt;x-noerd::dashboard-card heroicon="users" title="Customers" :value="128" /&gt;
&lt;x-noerd::dashboard-card heroicon="document-text" title="Invoices" :value="42" component="invoices-list" /&gt;
&lt;x-noerd::dashboard-card heroicon="globe-alt" title="Website" external="https://example.com" /&gt;
&lt;x-noerd::dashboard-card heroicon="star" title="Premium" :value="5" background="bg-yellow-50" /&gt;</code></pre>
    </details>

    {{-- Toolbar --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Toolbar</h3>
    <p class="text-sm text-gray-500 mb-3">Button group with separators, heroicons, wire:click actions, and loading states.</p>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <x-noerd::toolbar :buttons="$this->demoToolbarButtons" />
    </div>
    <details class="group mb-6">
        <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 py-1">Show code</summary>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code>&lt;x-noerd::toolbar :buttons="[
    ['action' =&gt; 'export', 'label' =&gt; 'Export', 'heroicon' =&gt; 'arrow-down-tray'],
    ['type' =&gt; 'separator'],
    ['action' =&gt; 'print', 'label' =&gt; 'Print', 'heroicon' =&gt; 'printer'],
    ['action' =&gt; 'refresh', 'label' =&gt; 'Refresh', 'loading' =&gt; 'Loading...'],
    ['action' =&gt; 'delete', 'label' =&gt; 'Delete', 'confirm' =&gt; 'Are you sure?'],
]" /&gt;</code></pre>
    </details>
</section>
