@props(['model' => null, 'label' => null, 'click' => null])

<!-- Toggle -->
<div
        x-data="{ value: $wire.entangle('{{$model}}').live }"
        class="flex items-center justify-center"
        x-noerd::id="['toggle-label']"
>
    <input type="hidden" name="sendNotifications" :value="value">

    <!-- Label -->
    <label
            @click="$refs.toggle.click(); $refs.toggle.focus()"
            :id="$id('toggle-label')"
            class="text-gray-900 font-medium"
    >
        {{$label}}
    </label>

    <!-- Button -->
    <button
        x-noerd::ref="toggle"
        wire:click="{{$click}}"
        @click="value = ! value"
        type="button"
        role="switch"
        :aria-checked="value"
        :aria-labelledby="$id('toggle-label')"
        :class="value ? '!bg-black' : '!bg-gray-200'"
        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
    >
        <span
            :class="value ? 'translate-x-noerd::5' : 'translate-x-noerd::0'"
            class="pointer-events-none inline-block h-5 w-5 translate-x-noerd::0 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
            aria-hidden="true"
        ></span>
    </button>
</div>
