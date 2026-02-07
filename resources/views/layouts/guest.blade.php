<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Noerd') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="/vendor/noerd/fonts/fonts.css">

</head>
<body class="bg-brand-bg h-full">

<div>
    <!-- Content -->
    <div>
        <main>
            {{ $slot }}
        </main>
    </div>
</div>

</body>
</html>
