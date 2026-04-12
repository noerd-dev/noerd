<section class="mb-12">
    <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-300 pb-2 mb-4">{{ __('ui_library_section_labels_errors') }}</h2>

    {{-- Input Label --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Input Label</h3>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <div class="flex flex-wrap gap-6">
            <x-noerd::input-label value="Normal Label" />
            <x-noerd::input-label value="Required Field" :required="true" />
        </div>
    </div>
    <details class="group mb-6">
        <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 py-1">Show code</summary>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code>&lt;x-noerd::input-label value="Field Name" /&gt;
&lt;x-noerd::input-label value="Required Field" :required="true" /&gt;</code></pre>
    </details>

    {{-- Input Error --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Input Error</h3>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <x-noerd::input-error :messages="['This field is required.', 'Must be at least 3 characters.']" />
    </div>
    <details class="group mb-6">
        <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 py-1">Show code</summary>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code>&lt;x-noerd::input-error :messages="$errors-&gt;get('detailData.name')" class="mt-2" /&gt;

&lt;!-- Or with static messages --&gt;
&lt;x-noerd::input-error :messages="['This field is required.']" /&gt;</code></pre>
    </details>
</section>
