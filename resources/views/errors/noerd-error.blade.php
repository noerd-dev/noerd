<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Error') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
        <div class="w-16 h-16 mx-auto mb-6 text-red-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
        </div>

        @if($type === 'app_not_assigned')
            <h1 class="text-xl font-semibold text-gray-900 mb-4">
                {{ __('App not available') }}
            </h1>
            <p class="text-gray-600 mb-6">
                {{ __('The app ":app" is not assigned to this tenant.', ['app' => $appName]) }}
            </p>
        @elseif($type === 'config_not_found')
            <h1 class="text-xl font-semibold text-gray-900 mb-4">
                {{ __('Configuration not found') }}
            </h1>
            <p class="text-gray-600 mb-4">
                {{ __('The required configuration file was not found:') }}
            </p>
            <code class="block bg-gray-100 text-sm text-red-600 px-4 py-2 rounded mb-6">
                {{ $configFile }}
            </code>
        @endif

        <a href="/" class="inline-flex items-center px-4 py-2 bg-brand-border text-white rounded-md hover:bg-brand-border/90">
            {{ __('Back to home') }}
        </a>
    </div>
</body>
</html>
