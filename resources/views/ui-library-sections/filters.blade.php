<section class="mb-12">
    <p class="text-sm text-gray-500 mb-4">Filter components used in list views. They require a list component context with <code class="text-xs bg-gray-100 px-1 rounded">listFilters</code> and <code class="text-xs bg-gray-100 px-1 rounded">storeActiveListFilters</code>.</p>
    <x-noerd::info-box>Requires a list component context with listFilters.</x-noerd::info-box>

    {{-- Picklist Filter --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Picklist Filter</h3>
    <p class="text-sm text-gray-500 mb-3">Dropdown filter with active state styling (dashed border when inactive, solid brand border when active).</p>
    <x-noerd::code-snippet>&lt;x-noerd::filters.picklist
    :filter="[
        'column' =&gt; 'status',
        'label' =&gt; 'Status',
        'options' =&gt; [
            'active' =&gt; 'Active',
            'inactive' =&gt; 'Inactive',
        ],
    ]"
    :value="$listFilters['status'] ?? ''"
/&gt;</x-noerd::code-snippet>

    {{-- Date Dropdown --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Date Dropdown Filter</h3>
    <p class="text-sm text-gray-500 mb-3">Date range filter with preset options and a custom date picker sub-panel.</p>
    <x-noerd::code-snippet>&lt;x-noerd::filters.date-dropdown
    :filter="[
        'column' =&gt; 'created_at',
        'label' =&gt; 'Created',
        'options' =&gt; [
            now()-&gt;subDays(7)-&gt;toDateString() =&gt; 'Last 7 days',
            now()-&gt;subDays(30)-&gt;toDateString() =&gt; 'Last 30 days',
            now()-&gt;subDays(90)-&gt;toDateString() =&gt; 'Last 90 days',
        ],
    ]"
    :value="$listFilters['created_at'] ?? ''"
/&gt;</x-noerd::code-snippet>
</section>
