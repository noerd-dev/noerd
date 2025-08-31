<div>
    <textarea 
        wire:model="{{$field}}" 
        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" 
        style="min-height: 200px;" 
        rows="8"
    >{!! $content !!}</textarea>
</div>
