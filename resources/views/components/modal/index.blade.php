<div
    x-data="{ open: false }"
    x-noerd::modelable="open"
    {{ $attributes }}
>
    {{ $slot }}
</div>
