<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('noerd::partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="bg-muted relative hidden h-full flex-col p-10 text-white lg:flex dark:border-e dark:border-neutral-800">
                @if(env('AUTH_BACKGROUND_IMAGE'))
                    <div class="absolute inset-0 bg-cover bg-center bg-no-repeat" 
                         style="background-image: url('{{ env('AUTH_BACKGROUND_IMAGE') }}');">
                        <div class="absolute inset-0 bg-black/50"></div>
                    </div>
                @else
                    <div class="absolute inset-0" style="background-color: {{ env('VITE_PRIMARY_COLOR', '#171717') }};"></div>
                @endif
                <a href="{{ route('noerd-home') }}" class="relative z-20 flex items-center text-lg font-medium" wire:navigate>

                    {{ config('app.name', 'Laravel') }}
                </a>
            </div>
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('noerd-home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md">
                            <x-noerd::app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                        </span>

                        <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
