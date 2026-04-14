<section class="mb-12">

    {{-- Picklist --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Picklist</h3>
    <p class="text-sm text-gray-500 mb-3">Select dropdown with options resolved from a picklist method on the component.</p>
    <x-noerd::info-box>Requires the Picklist trait and a resolvePicklistOptions method.</x-noerd::info-box>
    <div>
        <p class="text-xs font-medium text-gray-400 uppercase mt-3 mb-2">Code</p>
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
    </div>

    {{-- File Upload --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">File Upload</h3>
    <p class="text-sm text-gray-500 mb-3">File upload with optional multiple file support and type filtering.</p>
    <x-noerd::info-box>Requires the WithFileUploads trait in the Livewire component.</x-noerd::info-box>
    <div>
        <p class="text-xs font-medium text-gray-400 uppercase mt-3 mb-2">Code</p>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code>&lt;x-noerd::forms.file name="attachment" label="Upload File" /&gt;
&lt;x-noerd::forms.file name="documents" label="Documents" :multiple="true" accept=".pdf,.doc,.docx" /&gt;
&lt;x-noerd::forms.file name="images" label="Images" accept="image/*" :live="true" /&gt;</code></pre>
    </div>

    {{-- Image --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Image</h3>
    <p class="text-sm text-gray-500 mb-3">Image upload/selection with preview, delete, and media library integration.</p>
    <x-noerd::info-box>Requires the MediaResolver service and media module.</x-noerd::info-box>
    <div>
        <p class="text-xs font-medium text-gray-400 uppercase mt-3 mb-2">Code</p>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code>&lt;x-noerd::forms.image name="detailData.image_id" label="Image" /&gt;

# The component needs these methods (provided by NoerdDetail trait):
public function openSelectMediaModal(string $fieldName): void { ... }
public function deleteImage(string $fieldName): void { ... }</code></pre>
    </div>

    {{-- Relation Input --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Registered Relation Input</h3>
    <p class="text-sm text-gray-500 mb-3">Uses an explicitly registered relation type such as <code>customerRelation</code>. The list modal, detail modal and title resolution come from the registry.</p>
    <x-noerd::info-box>Relations must be registered in a module service provider. The generic <code>relation</code> type is no longer supported.</x-noerd::info-box>
    <div>
        <p class="text-xs font-medium text-gray-400 uppercase mt-3 mb-2">Code</p>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code># YAML configuration
- name: detailData.customer_id
  label: Customer
  type: customerRelation

# Service provider registration
$relationFieldRegistry-&gt;register('customerRelation', RelationFieldDefinition::model(
    listComponent: 'customers-list',
    detailComponent: 'customer-detail',
    modelClass: Customer::class,
    titleResolver: 'name',
));</code></pre>
    </div>

    {{-- Belongs to Many --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Belongs to Many</h3>
    <p class="text-sm text-gray-500 mb-3">Tag-like multi-select with search, filtering, and keyboard navigation.</p>
    <x-noerd::info-box>Requires an optionsMethod on the Livewire component.</x-noerd::info-box>
    <div>
        <p class="text-xs font-medium text-gray-400 uppercase mt-3 mb-2">Code</p>
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
    </div>

    {{-- Form Button --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Form Button</h3>
    <p class="text-sm text-gray-500 mb-3">A button rendered inside the form field grid that triggers a Livewire method.</p>
    <div>
        <p class="text-xs font-medium text-gray-400 uppercase mt-3 mb-2">Code</p>
        <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto font-mono"><code># YAML configuration
- name: generateReport
  label: Generate Report
  type: button

# Blade usage
&lt;x-noerd::forms.button name="generateReport" label="Generate Report" /&gt;</code></pre>
    </div>
</section>
