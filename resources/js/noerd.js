document.addEventListener('alpine:init', () => {
    Alpine.magic('modal', () => {
        return (component, args = {}, source = null) => {
            const params = { modalComponent: component, arguments: args };
            if (source) params.source = source;
            Livewire.dispatch('noerdModal', params);
        };
    });
});
