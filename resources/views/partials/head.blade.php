<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? env('APP_NAME') }}</title>

<link rel="stylesheet" href="/vendor/noerd/fonts/fonts.css">

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
