<div {{ $attributes->whereDoesntStartWith('class') }} {{ $attributes->merge(['class' => 'my-auto flex-1']) }}>
    <svg class="nc-icon mx-auto" xmlns="http://www.w3.org/2000/svg"
         x="0px" y="0px" width="20px" height="20px"
         viewBox="0 0 48 48">
        <g fill="currentColor" stroke-linecap="square" stroke-linejoin="miter"
           stroke-miterlimit="10">
            <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2" fill="none"></rect>
            <rect x="27" y="3" width="18" height="18" rx="2" stroke-width="2" fill="none"></rect>
            <rect x="3" y="27" width="18" height="18" rx="2" stroke-width="2" fill="none"></rect>
            <rect x="27" y="27" width="18" height="18" rx="2" stroke-width="2" fill="none"></rect>
            <line x1="8" y1="12" x2="16" y2="12" stroke-width="2" fill="none"></line>
            <circle cx="36" cy="12" r="4" stroke-width="2" fill="none"></circle>
            <line x1="12" y1="32" x2="12" y2="40" stroke-width="2" fill="none"></line>
            <line x1="8" y1="36" x2="16" y2="36" stroke-width="2" fill="none"></line>
            <path d="M32 32 L40 40 M40 32 L32 40" stroke-width="2" fill="none"></path>
        </g>
    </svg>
</div>
