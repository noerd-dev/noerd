@props([
    'label' => 'Code',
    'language' => 'blade',
])

@once
    <style>
        .noerd-code-snippet-panel {
            background: #030712;
            border: 1px solid #1f2937;
            border-radius: 0.5rem;
            box-shadow: inset 0 2px 4px 0 rgb(0 0 0 / 0.05);
            color: #f3f4f6;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.875rem;
            line-height: 1.5rem;
            overflow-x: auto;
            padding: 1rem 3rem 1rem 1rem;
        }

        .noerd-code-snippet-copy {
            align-items: center;
            background: rgb(31 41 55 / 0.95);
            border: 1px solid rgb(255 255 255 / 0.1);
            border-radius: 0.375rem;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            color: #d1d5db;
            display: inline-flex;
            height: 2rem;
            justify-content: center;
            opacity: 0;
            position: absolute;
            right: 0.5rem;
            top: 0.5rem;
            transition: background-color 150ms ease, color 150ms ease, opacity 150ms ease;
            width: 2rem;
            z-index: 10;
        }

        .noerd-code-snippet-copy:hover {
            background: #374151;
            color: #ffffff;
        }

        .noerd-code-snippet-copy:focus {
            opacity: 1;
            outline: 2px solid rgb(255 255 255 / 0.4);
            outline-offset: 2px;
        }

        .noerd-code-snippet-wrap:hover .noerd-code-snippet-copy,
        .noerd-code-snippet-wrap:focus-within .noerd-code-snippet-copy,
        .noerd-code-snippet-copy.is-copied {
            opacity: 1;
        }

        .noerd-code-snippet-copy.is-copied {
            color: #6ee7b7;
        }
    </style>
@endonce

<div {{ $attributes->merge(['class' => 'mt-3']) }}>
    <p class="text-xs font-medium text-gray-400 uppercase mb-2">{{ $label }}</p>

    <div
        class="noerd-code-snippet-wrap relative"
        x-data="noerdCodeSnippet()"
        x-init="highlight($refs.code)"
    >
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
