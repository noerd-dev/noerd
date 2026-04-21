@props(['tabNumber' => null, 'route' => null, 'routeParameters' => [], 'component' => null, 'arguments' => null, 'external' => null, 'active' => false])

@isset($tabNumber)
    <div class="inline-flex">
        <a  @click.prevent="currentTab= {{$tabNumber}}"
           class="-mb-[1px] cursor-pointer border-b-2 border-transparent text-gray-600 mr-6 hover:border-gray-500 focus:outline-none focus-visible:outline-none"
           :class="{'border-brand-primary! text-black!': currentTab == {{$tabNumber}} }">
        <span class="border-transparent p-0 py-3 rounded-sm group inline-flex items-center border-b-2 text-sm">
            {{ $slot }}
        </span>
        </a>
    </div>
@endisset

@if(isset($route) && ! isset($component))
    <div class="inline-flex">
        <a @if($external) target="_blank" @else wire:navigate @endif href="{{route($route, $routeParameters ?? null)}}"
           @class([
               '-mb-[1px] border-b-2 text-gray-600 mr-6 hover:border-gray-500 focus:outline-none focus-visible:outline-none',
               'border-brand-primary! text-black!' => $active,
               'border-transparent' => !$active,
           ])>
        <span class="border-transparent p-0 py-3 rounded-sm group inline-flex items-center border-b-2 text-sm">
            {{ $slot }}
        </span>
        </a>
    </div>
@endif

@isset($component)
    @php $componentRouteUrl = $route ? route($route, $routeParameters) : null; @endphp
    <div class="-mb-[1px] inline-flex items-center mr-6 border-b-2 border-transparent hover:border-gray-500">
        <a
            @if($componentRouteUrl) href="{{ $componentRouteUrl }}" @endif
            @click="if (!$event.metaKey && !$event.ctrlKey) { $event.preventDefault(); $modal('{{$component}}', {{json_encode($arguments ?? [])}}); }"
            class="cursor-pointer text-gray-600 focus:outline-none focus-visible:outline-none">
        <span class="p-0 py-3 rounded-sm group inline-flex items-center text-sm">
            {{ $slot }}
        </span>
        </a>
        @if($componentRouteUrl)
            <a href="{{ $componentRouteUrl }}"
               target="_blank"
               rel="noopener"
               class="pl-2 py-3 text-gray-500 hover:text-black focus:outline-none focus-visible:outline-none"
               aria-label="{{ __('Open in new tab') }}">
                <x-noerd::icons.external/>
            </a>
        @endif
    </div>
@endisset


