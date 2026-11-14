/**
 * Adds keys to the shared Alpine store `app` without replacing it.
 *
 * noerd/noerd and noerd/modal each own part of `$store.app` (the core the
 * active list of the keyboard navigation, the modal package the modal flags),
 * and their bundles load in no fixed order. Whichever runs first creates the
 * store; the other only adds the keys it does not find — Alpine.store(name,
 * value) would replace what the first bundle registered.
 */
export function extendAppStore(alpine, defaults) {
    const store = alpine.store('app');

    if (! store) {
        alpine.store('app', defaults);

        return;
    }

    Object.entries(defaults).forEach(([key, value]) => {
        if (! (key in store)) {
            store[key] = value;
        }
    });
}
