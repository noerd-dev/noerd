// Behavioural test for the shared Alpine store `app` (extendAppStore()).
// Run by `npm test`; exits non-zero on failure.
import assert from 'node:assert/strict';
import { extendAppStore } from '../../resources/js/app-store.js';

function fakeAlpine() {
    const stores = {};

    return {
        store(name, value) {
            if (value !== undefined) {
                stores[name] = value;
            }

            return stores[name];
        },
    };
}

const core = {
    currentId: null,
    setId(id) {
        this.currentId = id;
    },
};

// The core first, the modal package second.
let alpine = fakeAlpine();
extendAppStore(alpine, core);
extendAppStore(alpine, { modalOpen: false, currentId: 'never-overwritten' });
alpine.store('app').setId('crm::accounts-list');
assert.equal(alpine.store('app').currentId, 'crm::accounts-list', 'setId() of the core works');
assert.equal(alpine.store('app').modalOpen, false, 'the modal keys are added');

// The modal package first, the core second.
alpine = fakeAlpine();
extendAppStore(alpine, { modalOpen: true });
extendAppStore(alpine, { ...core });
assert.equal(alpine.store('app').modalOpen, true, 'the modal state survives the core');
alpine.store('app').setId('zz-list');
assert.equal(alpine.store('app').currentId, 'zz-list', 'the core keys are added');
