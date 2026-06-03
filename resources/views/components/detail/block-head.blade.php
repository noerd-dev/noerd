<div>
    <div class="text-sm font-semibold pb-2 leading-6 text-gray-900">{{$title}}</div>
    @if($description)
        <p class="mt-1 text-sm text-gray-500">
            {{$description ?? ''}}
        </p>
    @endif
</div>
