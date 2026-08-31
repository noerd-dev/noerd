{{-- The control is full-width below `xl` (where it lives in the stacked filter drawer)
     and inline-sized from `xl` on. `full` forces the full-width layout at every width. --}}
@props(['filter', 'value' => '', 'full' => false])

<select wire:change="storeActiveListFilters"
        wire:model.live="listFilters.{{ $filter['column'] }}"
        class="@if($value !== '') !border-brand-primary !border-solid !border-2 @endif {{ $full ? 'w-full' : 'max-xl:w-full xl:mr-4 xl:min-w-36 xl:max-w-48 xl:shrink-0' }} truncate rounded-md border border-dashed border-zinc-300 px-3 h-8 py-1 pr-8 text-sm leading-[1.375rem] focus:outline-none focus:ring-2 focus:ring-brand-border">
    <option value="">{{ $filter['label'] }}</option>
    @foreach($filter['options'] ?? [] as $key => $option)
        <option value="{{ $key }}">{{ $option }}</option>
    @endforeach
</select>
