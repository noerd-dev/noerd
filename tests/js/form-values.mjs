// Behavioural harness for resources/js/form-values.js. Run by FrontendScriptsTest.php.
import assert from 'node:assert/strict';

const { noerdLinkHref, hasId, withoutId, normalizeId } = await import('../../resources/js/form-values.js');

assert.equal(noerdLinkHref('example.com'), 'https://example.com', 'a bare host gets https');
assert.equal(noerdLinkHref('  www.example.com/a?b=1 '), 'https://www.example.com/a?b=1');
assert.equal(noerdLinkHref('http://example.com'), 'http://example.com');
assert.equal(noerdLinkHref('https://example.com'), 'https://example.com');
assert.equal(noerdLinkHref('/imprint'), '/imprint', 'relative path');
assert.equal(noerdLinkHref('#top'), '#top', 'anchor');
assert.equal(noerdLinkHref('mailto:a@example.com'), 'mailto:a@example.com');
assert.equal(noerdLinkHref('tel:+49301234'), 'tel:+49301234');
assert.equal(noerdLinkHref('//cdn.example.com/x'), 'https://cdn.example.com/x');
assert.equal(noerdLinkHref(''), '');

const uuid = '9f3a1c2e-0000-4000-8000-000000000001';
const ulid = '01HX7Z8Y6V5T4S3R2Q1P0N9M8K';

assert.ok(hasId([1, 2], '2'), 'numeric ids compare across types');
assert.ok(hasId([uuid], uuid));
assert.ok(! hasId([9], uuid), 'a UUID is not the number it starts with');
assert.ok(! hasId([1], ulid), 'a ULID is not the number it starts with');
assert.deepEqual(withoutId([uuid, ulid], uuid), [ulid]);
assert.deepEqual(withoutId([1, 2, 3], '2'), [1, 3]);
assert.equal(normalizeId('42'), 42, 'numeric keys go back as numbers');
assert.equal(normalizeId(uuid), uuid);
assert.equal(normalizeId(ulid), ulid);
