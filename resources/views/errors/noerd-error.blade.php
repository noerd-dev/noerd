<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ __('Error') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-100">
    <div class="w-full max-w-md rounded-lg bg-white p-8 text-center shadow-lg">
        @if ($type === 'app_access_denied')
            {{-- Friendly denial, not an error: the user simply has no access to this app. --}}
            <div class="mx-auto mb-6 h-16 w-16 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
            </div>
        @else
            <div class="mx-auto mb-6 h-16 w-16 text-red-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
        @endif

        @if ($type === 'app_access_denied')
            <div class="mb-4 text-xl font-semibold text-gray-900">{{ __('No access') }}</div>
            <p class="mb-1 text-gray-600">{{ __('You do not have permission to view this area.') }}</p>
            <p class="mb-6 text-gray-600">{{ __('Please contact your administrator to request access.') }}</p>
        @elseif ($type === 'app_not_assigned')
            <div class="mb-4 text-xl font-semibold text-gray-900">{{ __('App not available') }}</div>
            <p class="mb-6 text-gray-600">
                {{ __('The app ":app" is not assigned to this tenant.', ['app' => $appName]) }}
            </p>
        @elseif ($type === 'config_not_found')
            <div class="mb-4 text-xl font-semibold text-gray-900">{{ __('Configuration not found') }}</div>
            <p class="mb-4 text-gray-600">{{ __('The required configuration file was not found:') }}</p>
            <code class="mb-6 block rounded bg-gray-100 px-4 py-2 text-sm text-red-600"> {{ $configFile }} </code>
        @endif

        <a
            href="/"
            class="bg-brand-border hover:bg-brand-border/90 inline-flex items-center rounded-md px-4 py-2 text-white"
        >
            {{ __('Back to home') }}
        </a>
    </div>
</body>
</html>
