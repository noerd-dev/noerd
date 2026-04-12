<section class="mb-12">
    <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-300 pb-2 mb-4">{{ __('ui_library_section_form_inputs') }} (Advanced)</h2>

    {{-- Picklist --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Picklist</h3>
    <p class="text-sm text-gray-500 mb-3">Select dropdown with options resolved from a picklist method on the component.</p>
    <x-noerd::info-box>{{ __('ui_library_requires_picklist_trait') }}</x-noerd::info-box>
    <details class="group mb-6 mt-2">
        <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 py-1">Show code</summary>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code># YAML configuration
- name: detailData.status
  label: Status
  type: picklist
  picklistField: order_status

# The component needs a method like:
public function resolvePicklistOptions(string $field): array
{
    return match($field) {
        'order_status' =&gt; [
            'new' =&gt; 'New',
            'processing' =&gt; 'Processing',
            'completed' =&gt; 'Completed',
        ],
    };
}

# Blade usage
&lt;x-noerd::forms.picklist name="detailData.status" label="Status" picklistField="order_status" /&gt;</code></pre>
    </details>

    {{-- File Upload --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">File Upload</h3>
    <p class="text-sm text-gray-500 mb-3">File upload with optional multiple file support and type filtering.</p>
    <x-noerd::info-box>{{ __('ui_library_requires_file_uploads') }}</x-noerd::info-box>
    <details class="group mb-6 mt-2">
        <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 py-1">Show code</summary>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code>&lt;x-noerd::forms.file name="attachment" label="Upload File" /&gt;
&lt;x-noerd::forms.file name="documents" label="Documents" :multiple="true" accept=".pdf,.doc,.docx" /&gt;
&lt;x-noerd::forms.file name="images" label="Images" accept="image/*" :live="true" /&gt;</code></pre>
    </details>

    {{-- Image --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Image</h3>
    <p class="text-sm text-gray-500 mb-3">Image upload/selection with preview, delete, and media library integration.</p>
    <x-noerd::info-box>{{ __('ui_library_requires_media_resolver') }}</x-noerd::info-box>
    <details class="group mb-6 mt-2">
        <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 py-1">Show code</summary>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code>&lt;x-noerd::forms.image name="detailData.image_id" label="Image" /&gt;

# The component needs these methods (provided by NoerdDetail trait):
public function openSelectMediaModal(string $fieldName): void { ... }
public function deleteImage(string $fieldName): void { ... }</code></pre>
    </details>

    {{-- Relation Input --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Relation Input</h3>
    <p class="text-sm text-gray-500 mb-3">Opens a list modal to select a related record. Shows the relation title and allows clearing.</p>
    <x-noerd::info-box>{{ __('ui_library_requires_modal') }}</x-noerd::info-box>
    <details class="group mb-6 mt-2">
        <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 py-1">Show code</summary>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code># YAML configuration
- name: detailData.customer_id
  label: Customer
  type: relation
  relationField: relationTitles.customer_id
  modalComponent: customers-list

# Blade usage
&lt;x-noerd::forms.input-relation
    name="detailData.customer_id"
    label="Customer"
    modalComponent="customers-list"
    relationField="relationTitles.customer_id"
/&gt;

# Required event handler in the component
#[On('customerSelected')]
public function customerSelected($customerId): void
{
    $customer = Customer::find($customerId);
    $this-&gt;detailData['customer_id'] = $customer-&gt;id;
    $this-&gt;relationTitles['customer_id'] = $customer-&gt;name;
}</code></pre>
    </details>

    {{-- Belongs to Many --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Belongs to Many</h3>
    <p class="text-sm text-gray-500 mb-3">Tag-like multi-select with search, filtering, and keyboard navigation.</p>
    <x-noerd::info-box>{{ __('ui_library_requires_options_method') }}</x-noerd::info-box>
    <details class="group mb-6 mt-2">
        <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 py-1">Show code</summary>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code># YAML configuration
- name: tagIds
  label: Tags
  type: belongsToMany
  optionsMethod: getTagOptions

# Blade usage
&lt;x-noerd::forms.belongs-to-many
    name="tagIds"
    label="Tags"
    optionsMethod="getTagOptions"
/&gt;

# Required method on the component
public function getTagOptions(): array
{
    return Tag::pluck('name', 'id')-&gt;toArray();
}</code></pre>
    </details>

    {{-- Form Button --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Form Button</h3>
    <p class="text-sm text-gray-500 mb-3">A button rendered inside the form field grid that triggers a Livewire method.</p>
    <details class="group mb-6">
        <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 py-1">Show code</summary>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code># YAML configuration
- name: generateReport
  label: Generate Report
  type: button

# Blade usage
&lt;x-noerd::forms.button name="generateReport" label="Generate Report" /&gt;</code></pre>
    </details>
</section>
