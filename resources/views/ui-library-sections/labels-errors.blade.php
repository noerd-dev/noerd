<section class="mb-12">

    {{-- Input Label --}}
    <div class="text-sm font-semibold text-gray-700 mt-6 mb-2">Input Label</div>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <div class="flex flex-wrap gap-6">
            <x-noerd::input-label value="Normal Label" />
            <x-noerd::input-label value="Required Field" :required="true" />
        </div>
    </div>
    <x-noerd::code-snippet>&lt;x-noerd::input-label value="Field Name" /&gt;
&lt;x-noerd::input-label value="Required Field" :required="true" /&gt;</x-noerd::code-snippet>

    {{-- Input Error --}}
    <div class="text-sm font-semibold text-gray-700 mt-6 mb-2">Input Error</div>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <x-noerd::input-error :messages="['This field is required.', 'Must be at least 3 characters.']" />
    </div>
    <x-noerd::code-snippet>&lt;x-noerd::input-error :messages="$errors-&gt;get('detailData.name')" class="mt-2" /&gt;

&lt;!-- Or with static messages --&gt;
&lt;x-noerd::input-error :messages="['This field is required.']" /&gt;</x-noerd::code-snippet>
</section>
