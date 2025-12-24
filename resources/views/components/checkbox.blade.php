@props(['disabled' => false, 'id' => \Illuminate\Support\Str::random()])

<div class="relative flex items-start my-auto">
    <div class="flex h-6 items-center ">
        <input @if($disabled) disabled @endif id="{{$id}}"
               aria-describedby="comments-description"
               {{$attributes->whereDoesntStartWith('class')}}
               type="checkbox" class="h-4 w-4 border cursor-pointer rounded-sm border-gray-400 text-brand-primary focus:ring-brand-border">
    </div>
    <div class="ml-3 text-sm leading-6 ">
        <label for="{{$id}}" class="font-medium cursor-pointer text-gray-900">{{$slot}}</label>
    </div>
</div>
