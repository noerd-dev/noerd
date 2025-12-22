@props(['title', 'description' => null])

<div class="text-center">
    <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $title }}</h1>
    @if($description)
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $description }}</p>
    @endif
</div>
