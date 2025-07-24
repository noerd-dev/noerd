<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Liefertool') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Overpass:ital,wght@0,100..900;1,100..900&display=swap"
          rel="stylesheet">

</head>
<body class="bg-brand-bg h-full">

<div>
    <!-- Content -->
    <div>
        <main :class="showSidebar ? 'lg:pl-[360px]' : 'lg:pl-[122px]'">
            <div class="bg-white border border-gray-300 py-8 pt-[59px] min-h-screen">
                {{ $slot }}
            </div>
        </main>
    </div>

    <livewire:layout.top-bar></livewire:layout.top-bar>
</div>

</body>
</html>
