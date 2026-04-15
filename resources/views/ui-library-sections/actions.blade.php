<section class="mb-12">

    {{-- Toggle --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Toggle</h3>
    <p class="text-sm text-gray-500 mb-3">Switch toggle with label and Livewire entangle binding.</p>
    <div class="border rounded-lg p-6 bg-white mb-2">
        <x-noerd::toggl model="demoToggle" label="Enable notifications" click="demoAction" />
    </div>
    <x-noerd::code-snippet>&lt;x-noerd::toggl model="detailData.is_active" label="Active" click="toggleActive" /&gt;

&lt;!-- The model prop is entangled with Livewire via $wire.entangle() --&gt;
&lt;!-- The click prop triggers a wire:click action --&gt;</x-noerd::code-snippet>

    {{-- Action Message --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Action Message</h3>
    <p class="text-sm text-gray-500 mb-3">Transient feedback message that auto-hides after 2 seconds. Triggered by a Livewire event.</p>
    <x-noerd::code-snippet>&lt;x-noerd::action-message on="saved"&gt;
    Successfully saved.
&lt;/x-noerd::action-message&gt;

// Trigger from PHP:
$this-&gt;dispatch('saved');</x-noerd::code-snippet>

    {{-- Delete/Save Bar --}}
    <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-2">Delete/Save Bar</h3>
    <p class="text-sm text-gray-500 mb-3">Footer bar with save and delete buttons, success indicator, and confirmation dialog.</p>
    <x-noerd::info-box>Requires the NoerdDetail trait in the Livewire component.</x-noerd::info-box>
    <x-noerd::code-snippet>&lt;!-- In a page footer slot --&gt;
&lt;x-slot:footer&gt;
    &lt;x-noerd::delete-save-bar :showDelete="true" deleteMessage="Are you sure?" /&gt;
&lt;/x-slot:footer&gt;

&lt;!-- Save only (no delete) --&gt;
&lt;x-noerd::delete-save-bar :showDelete="false" /&gt;

&lt;!-- With footer components --&gt;
&lt;x-noerd::delete-save-bar
    :showDelete="true"
    :modelId="$this-&gt;modelId"
    :footerComponents="[
        ['component' =&gt; 'activity-log', 'requiresId' =&gt; true],
    ]"
/&gt;

&lt;!-- The component calls wire:click="store" and wire:click="delete" --&gt;</x-noerd::code-snippet>
</section>
