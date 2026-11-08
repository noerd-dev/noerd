@props(['title' => null, 'description' => null])

{{-- Shared shell of the guest auth screens: logo + heading on the left, brand image or video on the right. --}}
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
    @php
        $backgroundImage = config('noerd.branding.auth_background_image');
        $backgroundVideo = config('noerd.branding.auth_background_video');
        $backgroundVideoType = match (strtolower(pathinfo((string) parse_url((string) $backgroundVideo, PHP_URL_PATH), PATHINFO_EXTENSION))) {
            'mp4', 'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg', 'ogv' => 'video/ogg',
            'mov' => 'video/quicktime',
            default => null,
        };
    @endphp
    <div class="relative hidden w-0 flex-1 bg-black lg:block">
        @if($backgroundImage)
            <img src="{{ $backgroundImage }}" alt="" class="absolute inset-0 size-full object-cover" />
        @endif
        @if($backgroundVideo)
            {{-- The image stays underneath: it shows while the video loads, when the browser cannot play it and under reduced motion. --}}
            <video
                autoplay
                muted
                loop
                playsinline
                preload="auto"
                aria-hidden="true"
                @if($backgroundImage) poster="{{ $backgroundImage }}" @endif
                class="absolute inset-0 size-full object-cover motion-reduce:hidden"
            >
                <source src="{{ $backgroundVideo }}" @if($backgroundVideoType) type="{{ $backgroundVideoType }}" @endif />
            </video>
        @endif
    </div>
</div>
