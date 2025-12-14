@props([
    'emailSubject' => '',
    'sampleData' => [],
    'previewHtml' => '',
])

<!-- Email Preview Modal -->
<div x-data="{ show: $wire.entangle('showPreview') }"
     x-show="show"
     x-effect="document.body.style.overflow = show ? 'hidden' : ''"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-300"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center"
     aria-modal="true"
     role="dialog"
     @keydown.escape.window="$wire.closePreview()"
     style="display: none;">

    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-800/50" @click="$wire.closePreview()"></div>

    <!-- Modal Content -->
    <div x-show="show"
         x-transition:enter="transition transform ease-out duration-100"
         x-transition:enter-start="translate-y-1/2 opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition transform ease-in duration-100"
         x-transition:leave-start="translate-y-0 opacity-100"
         x-transition:leave-end="translate-y-full opacity-0"
         class="relative w-full max-w-4xl max-h-[90vh] bg-white rounded shadow-sm overflow-hidden my-auto mx-4">

        <!-- Close Button -->
        <button @click="$wire.closePreview()"
                type="button"
                class="absolute right-0 top-4 pt-2 pr-6 z-10">
            <div class="hover:bg-gray-100 hover:text-black border rounded-sm p-1.5 text-gray-600 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                <span class="sr-only">{{ __('noerd_close_modal') }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                          clip-rule="evenodd"/>
                </svg>
            </div>
        </button>

        <div class="flex flex-col max-h-[90vh]">
            <!-- Modal Header -->
            <div class="p-6 pb-0">
                <h3 class="text-xl font-semibold text-gray-900">{{ __('noerd_email_preview') }}</h3>
                <p class="text-sm text-gray-600 mt-1">
                    {{ __('noerd_email_preview_desc') }}
                </p>
            </div>

            <!-- Email Subject Preview -->
            @if(!empty($emailSubject))
                <div class="px-6 py-3 mt-4 mx-6 bg-gray-50 border border-gray-200 rounded">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                        {{ __('noerd_subject') }}
                    </div>
                    <div class="text-base font-medium text-gray-900">
                        {{ str_replace(
                            array_keys($sampleData),
                            array_values($sampleData),
                            $emailSubject
                        ) }}
                    </div>
                </div>
            @endif

            <!-- Email Body Preview -->
            <div class="p-6 flex-1 overflow-y-auto">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                    {{ __('noerd_content') }}
                </div>
                <div class="border border-gray-200 rounded overflow-hidden">
                    <iframe
                        srcdoc="{!! str_replace('"', '&quot;', $previewHtml) !!}"
                        class="w-full h-[500px] bg-white"
                        sandbox="allow-same-origin"
                        title="Email Preview">
                    </iframe>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex w-full border-t border-gray-300 py-4 px-6">
                <div class="ml-auto">
                    <x-noerd::buttons.secondary @click="$wire.closePreview()">
                        {{ __('noerd_close') }}
                    </x-noerd::buttons.secondary>
                </div>
            </div>
        </div>
    </div>
</div>
