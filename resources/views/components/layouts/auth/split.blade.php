<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('noerd::partials.head')
</head>
<body class="min-h-screen bg-white antialiased">
<div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
    <div class="bg-muted relative hidden h-full flex-col p-10 text-brand-highlight lg:flex ">
        <div class="absolute inset-0 bg-brand-highlight/20"></div>
        <a href="{{ route('dashboard') }}" class="relative z-20 flex items-center text-lg font-medium" wire:navigate>
                    <span class="flex h-10 w-10 items-center justify-center rounded-md">
                        <x-noerd::app-logo-icon class="mr-2 h-7 fill-current text-brand-highlight"/>
                    </span>
            {{ config('app.name', 'Laravel') }}
        </a>
    </div>
    <div class="w-full lg:p-8">
        <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
            <a href="{{ route('dashboard') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden"
               wire:navigate>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md">
                            <x-noerd::app-logo-icon class="size-9 fill-current text-black "/>
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
