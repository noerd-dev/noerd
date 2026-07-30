@props(['model' => null, 'label' => null, 'click' => null])

<!-- Toggle -->
<div
    x-data="{ value: $wire.entangle('{{ $model }}').live }"
    class="flex items-center justify-center"
    x-noerd::id="['toggle-label']"
>
    <input type="hidden" name="sendNotifications" :value="value" />

    <!-- Label -->
    <label
        @click="
            $refs.toggle.click();
            $refs.toggle.focus();
        "
        :id="$id('toggle-label')"
        class="font-medium text-gray-900"
    >
        {{ $label }}
    </label>

    <!-- Button -->
    <button
        x-noerd::ref="toggle"
        wire:click="{{ $click }}"
        @click="value = ! value"
        type="button"
        role="switch"
        :aria-checked="value"
        :aria-labelledby="$id('toggle-label')"
        :class="value ? '!bg-black' : '!bg-gray-200'"
        class="focus:ring-brand-border relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
    >
        <span
            :class="value ? 'translate-x-5' : 'translate-x-0'"
            class="pointer-events-none inline-block h-5 w-5 translate-x-0 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
            aria-hidden="true"
        ></span>
    </button>
</div>
