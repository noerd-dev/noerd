@props(['title' => null, 'description' => null])

{{-- Shared shell of the guest auth screens: logo + heading on the left, brand image on the right. --}}
<div class="flex min-h-[calc(100dvh_-_var(--environment-banner-height,0px))] items-stretch">
    <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
        <div class="mx-auto w-full max-w-sm lg:w-96">
            <div>
                <x-noerd::application-logo class="h-10 w-auto" />
                @if($title)
                    <div class="mt-8 text-2xl/9 font-bold tracking-tight text-gray-900">
                        {{ $title }}
                    </div>
                @endif
                @if($description)
                    <p class="mt-2 text-sm/6 text-gray-500">
                        {{ $description }}
                    </p>
                @endif
            </div>

            <x-noerd::auth-session-status class="mt-6" :status="session('status')" />

            <div class="mt-10">
                {{ $slot }}
            </div>
        </div>
    </div>
    <div class="relative hidden w-0 flex-1 bg-black lg:block">
        @if(config('noerd.branding.auth_background_image'))
            <img src="{{ config('noerd.branding.auth_background_image') }}" alt="" class="absolute inset-0 size-full object-cover" />
        @endif
    </div>
</div>
