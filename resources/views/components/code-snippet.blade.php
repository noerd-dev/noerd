@props([
    'label' => 'Code',
    'language' => 'blade',
])


<div {{ $attributes->merge(['class' => 'mt-3']) }}>
    <p class="mb-2 text-xs font-medium text-gray-400 uppercase">{{ $label }}</p>

    <div class="noerd-code-snippet-wrap relative" x-data="noerdCodeSnippet()" x-init="highlight($refs.code)">
        <button
            type="button"
            class="noerd-code-snippet-copy"
            :class="{ 'is-copied': copied }"
            :aria-label="copied ? 'Code copied' : 'Copy code'"
            @click="copy($refs.code)"
        >
            <span x-show="! copied">
                <x-icon name="clipboard-document" class="h-4 w-4" />
            </span>
            <span x-cloak x-show="copied">
                <x-icon name="check" class="h-4 w-4" />
            </span>
        </button>

        <pre class="noerd-code-snippet-panel"><code x-ref="code" class="noerd-code-snippet language-{{ $language }}">{{ $slot }}</code></pre>
    </div>
</div>
