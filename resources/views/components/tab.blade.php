@props(['tabNumber' => null, 'route' => null, 'component' => null, 'arguments' => null])

@isset($tabNumber)

    <div class="inline-flex">
        <a  @click.prevent="currentTab= {{$tabNumber}}"
           class="-mb-[1px] cursor-pointer border-b-2 border-transparent text-gray-600 mr-6 hover:border-gray-500"
           :class="{'border-brand-highlight! text-black!': currentTab == {{$tabNumber}} }">
        <span class="border-transparent p-0 py-3 rounded-sm group inline-flex items-center border-b-2 text-sm">
            {{ $slot }}
        </span>
        </a>
    </div>
@endisset

@isset($route)
    <div class="inline-flex">
        {{-- Removed   :class="{'border-brand-highlight! text-black!': currentTab == {{$tabNumber}} }" because it occures a java script error --}}
        <a target="_blank" href="{{route($route, $routeParameters ?? null)}}" class="-mb-[1px] border-b-2 border-transparent text-gray-600 mr-6 hover:border-gray-500">
        <span class="border-transparent p-0 py-3 rounded-sm group inline-flex items-center border-b-2 text-sm">
            {{ $slot }}
        </span>
        </a>
    </div>
@endisset

@isset($component)
    <div class="inline-flex">
        <a wire:click="$dispatch('noerdModal', {component: '{{$component}}', arguments: {{json_encode($arguments ?? [])}}})"
           class="-mb-[1px] cursor-pointer border-b-2 border-transparent mr-6 text-gray-600 mr-6 hover:border-gray-500">
        <span class="border-transparent p-0 py-3 rounded-sm group inline-flex items-center border-b-2 text-sm">
            {{ $slot }}
            <span class="pl-2">
                <x-noerd::icons.external/>
            </span>
        </span>
        </a>
    </div>
@endisset


