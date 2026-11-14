/**
 * Small value rules of the form fields in noerd.js, kept pure so
 * tests/js/form-values.mjs can prove them without a browser.
 */

/**
 * The href a rich-text link is stored with. A bare host ("example.com") gets
 * https:// — the browser would otherwise resolve it against the current page.
 * Relative paths, anchors, mail and phone links and anything that already
 * names a scheme stay as typed (the server-side sanitiser decides which
 * schemes survive).
 *
 * @param {string} url
 * @returns {string} '' for an empty input
 */
export function noerdLinkHref(url) {
    const href = String(url ?? '').trim();

    if (href === '') return '';

    if (href.startsWith('//')) return 'https:' + href;
    if (/^(\/|#|\?|\.)/.test(href)) return href;
    if (/^(mailto|tel):/i.test(href)) return href;
    if (/^[a-z][a-z0-9+.-]*:\/\//i.test(href)) return href;

    return 'https://' + href;
}

/**
 * Ids of related records are compared as strings: an integer key, a UUID and a
 * ULID alike. parseInt() turned "01HX…" into 1 and "9f3a-…" into 9.
 */
export function sameId(a, b) {
    return String(a) === String(b);
}

/**
 * @param {Array<string|number>} ids
 */
export function hasId(ids, id) {
    return (ids || []).some((item) => sameId(item, id));
}

/**
 * @param {Array<string|number>} ids
 */
export function withoutId(ids, id) {
    return (ids || []).filter((item) => ! sameId(item, id));
}

/**
 * The id as it is written back: an option key is always a string in the
 * browser, a numeric one goes back as the number the server sent.
 */
export function normalizeId(id) {
    return typeof id === 'string' && /^\d+$/.test(id) && id.length < 16 ? Number(id) : id;
}
