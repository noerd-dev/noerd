@props([
    /** The text next to the check mark; defaults to the generic "Successfully saved". */
    'message' => null,
    /** The boolean Livewire property that shows the indicator; reset by the component itself after 3 s. */
    'property' => 'showSuccessIndicator',
])

{{-- The transient "saved" feedback every detail shows left of its footer buttons
     (x-noerd::delete-save-bar). Any component with a save action renders the same
     one: set the property to true after persisting, the indicator fades out and
     resets it. --}}
<div
    x-show="$wire.{{ $property }}"
    x-transition.out.opacity.duration.1000ms
    x-effect="if($wire.{{ $property }}) setTimeout(() => $wire.{{ $property }} = false, 3000)"
    {{ $attributes->merge(['class' => 'flex']) }}
>
    <div class="ml-auto flex">
        <div class="shrink-0">
            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path
                    fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                    clip-rule="evenodd"
                />
            </svg>
        </div>
        <div class="ml-3">
            <p class="text-sm font-medium text-green-800">{{ $message ?: __('Successfully saved') }}</p>
        </div>
    </div>
</div>
