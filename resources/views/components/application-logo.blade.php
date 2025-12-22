@props(['class' => ''])

@if( config('noerd.branding.logo') )
    <img src="{{ config('noerd.branding.logo') }}"
         {{ $attributes->merge(['class' => $class]) }} alt="{{ config('app.name') }}">
@endif