<section class="mb-12">

    {{-- Markdown --}}
    <div class="text-sm font-semibold text-gray-700 mt-6 mb-2">Markdown</div>
    <p class="text-sm text-gray-500 mb-3">Renders markdown content as HTML with support for bold, italic, links, lists, and code.</p>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <x-noerd::markdown content="**Bold text** and *italic text* with a [link](https://example.com).

- List item one
- List item two

`Inline code` is also supported." />
    </div>
    <x-noerd::code-snippet>&lt;x-noerd::markdown content="**Bold** and *italic* with a [link](https://example.com)." /&gt;
&lt;x-noerd::markdown :content="$article-&gt;body" class="prose" /&gt;</x-noerd::code-snippet>

    {{-- Tabs --}}
    <div class="text-sm font-semibold text-gray-700 mt-6 mb-2">Tabs</div>
    <p class="text-sm text-gray-500 mb-3">Tab container supporting three modes: route-based, component-modal, and simple numbered tabs.</p>
    <x-noerd::info-box>Requires a YAML layout configuration with tabs defined.</x-noerd::info-box>
    <x-noerd::code-snippet language="yaml"># YAML tab definition
tabs:
  - number: 1
    label: module_tab_general
  - number: 2
    label: module_tab_settings
fields:
  - name: detailData.name
    label: Name
    type: text
    colspan: 6
  - name: detailData.setting_a
    label: Setting A
    type: checkbox
    tab: 2

# Blade usage
&lt;x-noerd::tabs :layout="$pageLayout" /&gt;

&#64;foreach($pageLayout['tabs'] ?? [['number' =&gt; 1]] as $tab)
    &lt;div x-show="currentTab === &#123;&#123; $tab['number'] &#125;&#125;"&gt;
        &#64;php
            $tabFields = array_filter(
                $pageLayout['fields'] ?? [],
                fn($f) =&gt; ($f['tab'] ?? 1) === $tab['number']
            );
            $tabLayout = array_merge(
                $pageLayout,
                ['fields' =&gt; array_values($tabFields)]
            );
        &#64;endphp
        &#64;include('noerd::components.detail.block', $tabLayout)
    &lt;/div&gt;
&#64;endforeach</x-noerd::code-snippet>

    {{-- Dropzone --}}
    <div class="text-sm font-semibold text-gray-700 mt-6 mb-2">Dropzone</div>
    <p class="text-sm text-gray-500 mb-3">Drag-and-drop file upload zone with progress indicator, file list, and validation.</p>
    <x-noerd::info-box>Requires the WithFileUploads trait in the Livewire component.</x-noerd::info-box>
    <x-noerd::code-snippet>&lt;livewire:noerd::dropzone
    wire:model="files"
    :rules="['mimes:pdf,jpg,png', 'max:10240']"
    :multiple="true"
/&gt;

&lt;!-- Single file upload --&gt;
&lt;livewire:noerd::dropzone
    wire:model="document"
    :rules="['mimes:pdf', 'max:5120']"
    :multiple="false"
/&gt;

# Listen for file events
#[On('files-updated')]
public function filesUpdated(array $files): void { ... }

#[On('files-cleared')]
public function filesCleared(): void { ... }</x-noerd::code-snippet>
</section>
