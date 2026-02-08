<svg {{ $attributes->whereDoesntStartWith('class') }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
    {{ $attributes->merge(['class' => 'w-4 h-4']) }}>
    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
</svg>
