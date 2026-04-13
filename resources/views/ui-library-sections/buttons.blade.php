<section class="mb-12">
    <p class="text-sm text-gray-500 mb-4">The button component supports 6 variants, heroicons, and wire loading states.</p>

    {{-- Variants --}}
    <div class="border rounded-lg p-6 bg-white mb-2">
        <p class="text-xs font-medium text-gray-400 uppercase mb-3">Variants</p>
        <div class="flex flex-wrap items-center gap-3">
            <x-noerd::button variant="primary">Primary</x-noerd::button>
            <x-noerd::button variant="secondary">Secondary</x-noerd::button>
            <x-noerd::button variant="danger">Danger</x-noerd::button>
            <x-noerd::button variant="pill">Pill</x-noerd::button>
            <x-noerd::button variant="ghost">Ghost</x-noerd::button>
            <x-noerd::button variant="icon" icon="pencil" />
        </div>
    </div>
    <div>
        <p class="text-xs font-medium text-gray-400 uppercase mt-3 mb-2">Code</p>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code>&lt;x-noerd::button variant="primary"&gt;Primary&lt;/x-noerd::button&gt;
&lt;x-noerd::button variant="secondary"&gt;Secondary&lt;/x-noerd::button&gt;
&lt;x-noerd::button variant="danger"&gt;Danger&lt;/x-noerd::button&gt;
&lt;x-noerd::button variant="pill"&gt;Pill&lt;/x-noerd::button&gt;
&lt;x-noerd::button variant="ghost"&gt;Ghost&lt;/x-noerd::button&gt;
&lt;x-noerd::button variant="icon" icon="pencil" /&gt;</code></pre>
    </div>

    {{-- Sizes --}}
    <div class="border rounded-lg p-6 bg-white mb-2">
        <p class="text-xs font-medium text-gray-400 uppercase mb-3">Sizes</p>
        <div class="flex flex-wrap items-center gap-3">
            <x-noerd::button size="sm">Small</x-noerd::button>
            <x-noerd::button>Default (md)</x-noerd::button>
            <x-noerd::button size="lg">Large</x-noerd::button>
            <x-noerd::button variant="icon" size="sm" icon="pencil" />
            <x-noerd::button variant="icon" icon="pencil" />
            <x-noerd::button variant="icon" size="lg" icon="pencil" />
        </div>
    </div>
    <div>
        <p class="text-xs font-medium text-gray-400 uppercase mt-3 mb-2">Code</p>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code>&lt;x-noerd::button size="sm"&gt;Small&lt;/x-noerd::button&gt;
&lt;x-noerd::button&gt;Default (md)&lt;/x-noerd::button&gt;
&lt;x-noerd::button size="lg"&gt;Large&lt;/x-noerd::button&gt;
&lt;x-noerd::button variant="icon" size="sm" icon="pencil" /&gt;
&lt;x-noerd::button variant="icon" icon="pencil" /&gt;
&lt;x-noerd::button variant="icon" size="lg" icon="pencil" /&gt;</code></pre>
    </div>

    {{-- With Icons --}}
    <div class="border rounded-lg p-6 bg-white mb-2">
        <p class="text-xs font-medium text-gray-400 uppercase mb-3">With Icons</p>
        <div class="flex flex-wrap items-center gap-3">
            <x-noerd::button icon="check-circle">Save</x-noerd::button>
            <x-noerd::button variant="secondary" icon="arrow-down-tray">Download</x-noerd::button>
            <x-noerd::button variant="danger">Delete</x-noerd::button>
            <x-noerd::button variant="icon" icon="pencil" />
            <x-noerd::button variant="icon" icon="trash" />
            <x-noerd::button variant="icon" icon="plus" />
        </div>
    </div>
    <div>
        <p class="text-xs font-medium text-gray-400 uppercase mt-3 mb-2">Code</p>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code>&lt;x-noerd::button icon="check-circle"&gt;Save&lt;/x-noerd::button&gt;
&lt;x-noerd::button variant="secondary" icon="arrow-down-tray"&gt;Download&lt;/x-noerd::button&gt;
&lt;x-noerd::button variant="danger"&gt;Delete&lt;/x-noerd::button&gt;
&lt;x-noerd::button variant="icon" icon="pencil" /&gt;
&lt;x-noerd::button variant="icon" icon="trash" /&gt;
&lt;x-noerd::button variant="icon" icon="plus" /&gt;

&lt;!-- With wire loading spinner --&gt;
&lt;x-noerd::button icon="check-circle" wireTarget="store"&gt;Save&lt;/x-noerd::button&gt;</code></pre>
    </div>

    {{-- All Props --}}
    <div>
        <p class="text-xs font-medium text-gray-400 uppercase mt-3 mb-2">All props</p>
        <div class="mt-2 overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200 rounded-lg">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-700">Prop</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-700">Default</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-700">Options</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr><td class="px-4 py-2 font-mono">variant</td><td class="px-4 py-2">'primary'</td><td class="px-4 py-2">primary, secondary, danger, pill, ghost, icon</td></tr>
                    <tr><td class="px-4 py-2 font-mono">size</td><td class="px-4 py-2">'md'</td><td class="px-4 py-2">sm, md, lg</td></tr>
                    <tr><td class="px-4 py-2 font-mono">icon</td><td class="px-4 py-2">null</td><td class="px-4 py-2">Any heroicon name (e.g. check-circle, trash, pencil)</td></tr>
                    <tr><td class="px-4 py-2 font-mono">wireTarget</td><td class="px-4 py-2">null</td><td class="px-4 py-2">Livewire method name for loading spinner</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
