@props(['icon', 'class' => ''])

@if(str_starts_with($icon, 'heroicon:'))
    @php
        // Format: heroicon:variant:name (e.g., heroicon:outline:academic-cap)
        $parts = explode(':', $icon);
        $variant = $parts[1] ?? 'outline';
        $name = $parts[2] ?? '';
    @endphp
    {{-- Wrap heroicon in same structure as noerd icons for consistent styling --}}
    <div {{ $attributes->whereDoesntStartWith('class') }} {{ $attributes->merge(['class' => 'my-auto flex-1 ' . $class]) }}>
        <x-icon :name="$name" :variant="$variant" class="mx-auto w-5 h-5" style="stroke-width: 1.5;" />
    </div>
@else
    <x-dynamic-component :component="$icon" {{ $attributes->merge(['class' => $class]) }} />
@endif
