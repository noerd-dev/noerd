@if (config('noerd.branding.logo'))
    <img
        src="{{ config('noerd.branding.logo') }}"
        {{ $attributes }}
        alt="{{ config('app.name') }}"
    />
@endif
