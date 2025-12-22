@props(['class' => ''])

<img src="{{ config('noerd.branding.logo', '/svg/liefertool.svg') }}" {{ $attributes->merge(['class' => $class]) }} alt="{{ config('app.name') }}">
