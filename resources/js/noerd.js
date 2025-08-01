//import './bootstrap';
import '../css/noerd.css';

import sort from '@alpinejs/sort'

Alpine.plugin(sort)

Alpine.store('globalState', {
    open: true,
});

Alpine.store('app', {
    currentId: 200,
    setId(id) {
        this.currentId = id;
    }
});
