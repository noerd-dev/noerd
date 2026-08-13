{{-- Friendly access-denied state shown in place of a read-restricted list or
     detail (see the noerd.object-read gate / AccessHelper). Deliberately not an error page: the
     navigation stays usable, the user is pointed to their administrator. --}}
<div class="flex flex-1 flex-col items-center justify-center px-6 py-24 text-center">
    <x-heroicons::outline.lock-closed class="h-10 w-10 text-gray-400" />
    <p class="mt-4 text-sm font-medium text-gray-700">{{ __('You do not have permission to view this area.') }}</p>
    <p class="mt-1 text-sm text-gray-500">{{ __('Please contact your administrator to request access.') }}</p>
</div>
