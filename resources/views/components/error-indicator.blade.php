@props([
    /** The text next to the warning sign; defaults to the generic "Not saved" hint. */
    'message' => null,
])

{{-- The counterpart of x-noerd::success-indicator: a save the validation refused.
     Shown while the page chrome (noerdPage) holds `saveFailed` — set by the
     `noerd-validation-failed` event of ValidationFeedbackHook, cleared by the next
     save that passes. It stays until then: the reader may be on another tab than
     the field with the error. typeof covers a bar outside a page scope. --}}
<div
    x-show="typeof saveFailed !== 'undefined' && saveFailed"
    role="alert"
    data-noerd-save-failed
    {{ $attributes->merge(['class' => 'flex']) }}
>
    <div class="ml-auto flex">
        <div class="shrink-0">
            <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path
                    fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z"
                    clip-rule="evenodd"
                />
            </svg>
        </div>
        <div class="ml-3">
            <p class="text-sm font-medium text-red-700">{{ $message ?: __('Not saved – please check the marked fields') }}</p>
        </div>
    </div>
</div>
