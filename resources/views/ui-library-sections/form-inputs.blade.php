<section class="mb-12">

    {{-- Text Input --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Text Input</h3>
    <p class="text-sm text-gray-500 mb-3">Basic text input with label, error handling, readonly, and live binding support.</p>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-noerd::forms.input name="demoText" label="Text" />
            <x-noerd::forms.input name="demoEmail" label="Email" type="email" />
            <x-noerd::forms.input name="demoNumber" label="Number" type="number" />
            <x-noerd::forms.input name="demoDate" label="Date" type="date" />
            <x-noerd::forms.input name="demoTime" label="Time" type="time" />
            <x-noerd::forms.input name="demoText" label="Readonly" :readonly="true" />
        </div>
    </div>
    <x-noerd::code-snippet>&lt;x-noerd::forms.input name="detailData.name" label="Name" /&gt;
&lt;x-noerd::forms.input name="detailData.email" label="Email" type="email" /&gt;
&lt;x-noerd::forms.input name="detailData.count" label="Count" type="number" /&gt;
&lt;x-noerd::forms.input name="detailData.date" label="Date" type="date" /&gt;
&lt;x-noerd::forms.input name="detailData.time" label="Time" type="time" /&gt;
&lt;x-noerd::forms.input name="detailData.name" label="Readonly" :readonly="true" /&gt;
&lt;x-noerd::forms.input name="detailData.name" label="Live" :live="true" /&gt;
&lt;x-noerd::forms.input name="detailData.name" label="Required" :required="true" /&gt;</x-noerd::code-snippet>

    {{-- Textarea --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Textarea</h3>
    <p class="text-sm text-gray-500 mb-3">Multi-line textarea with configurable rows.</p>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <div class="max-w-md">
            <x-noerd::forms.input-textarea name="demoTextarea" label="Description" rows="4" />
        </div>
    </div>
    <x-noerd::code-snippet>&lt;x-noerd::forms.input-textarea name="detailData.description" label="Description" rows="4" /&gt;
&lt;x-noerd::forms.input-textarea name="detailData.notes" label="Notes" :readonly="true" :required="true" /&gt;</x-noerd::code-snippet>

    {{-- Checkbox --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Checkbox</h3>
    <p class="text-sm text-gray-500 mb-3">Checkbox with label and boolean binding.</p>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <x-noerd::forms.checkbox name="demoCheckbox" label="Active" />
    </div>
    <x-noerd::code-snippet>&lt;x-noerd::forms.checkbox name="detailData.is_active" label="Active" /&gt;
&lt;x-noerd::forms.checkbox name="detailData.send_email" label="Send email" :live="true" /&gt;</x-noerd::code-snippet>

    {{-- Select --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Select</h3>
    <p class="text-sm text-gray-500 mb-3">Dropdown with options from configuration or a computed property.</p>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <div class="max-w-xs">
            <x-noerd::forms.input-select name="demoSelect" label="Choose option" :options="$this->demoSelectOptions" />
        </div>
    </div>
    <x-noerd::code-snippet>&lt;x-noerd::forms.input-select
    name="detailData.status"
    label="Status"
    :options="[
        ['value' =&gt; 'draft', 'label' =&gt; 'Draft'],
        ['value' =&gt; 'published', 'label' =&gt; 'Published'],
    ]"
/&gt;

&lt;!-- With live binding --&gt;
&lt;x-noerd::forms.input-select
    name="detailData.type"
    label="Type"
    :options="$this-&gt;typeOptions"
    :live="true"
/&gt;</x-noerd::code-snippet>

    {{-- Currency Input --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Currency Input</h3>
    <p class="text-sm text-gray-500 mb-3">Locale-aware currency input with thousand separators and currency symbol.</p>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <div class="max-w-xs">
            <x-noerd::forms.input-currency name="demoCurrency" label="Amount" />
        </div>
    </div>
    <x-noerd::code-snippet>&lt;x-noerd::forms.input-currency name="detailData.price" label="Price" /&gt;
&lt;x-noerd::forms.input-currency name="detailData.total" label="Total" :readonly="true" /&gt;</x-noerd::code-snippet>

    {{-- Color Picker --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Color Picker</h3>
    <p class="text-sm text-gray-500 mb-3">Hex color text field combined with a native color picker.</p>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <div class="max-w-xs">
            <x-noerd::forms.color-hex name="demoColor" label="Brand Color" />
        </div>
    </div>
    <x-noerd::code-snippet>&lt;x-noerd::forms.color-hex name="detailData.color" label="Brand Color" /&gt;
&lt;x-noerd::forms.color-hex name="detailData.color" label="Color" :live="true" /&gt;</x-noerd::code-snippet>
</section>
