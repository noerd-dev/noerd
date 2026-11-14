// Behavioural harness for the currency field (resources/js/currency.js): what
// the reader types must become the amount they meant — or be rejected, never
// guessed. Run by FrontendScriptsTest.php (node <this file>); exits non-zero on failure.
import assert from 'node:assert/strict';

const { parseCurrency, formatCurrency, editableCurrency } = await import('../../resources/js/currency.js');

const de = (input) => parseCurrency(input, ',');
const en = (input) => parseCurrency(input, '.');
const ok = (value) => ({ valid: true, value });
const rejected = { valid: false, value: null };

// The locale's own notation.
assert.deepEqual(de('1.234,56'), ok(1234.56), 'grouped amount in a comma locale');
assert.deepEqual(de('12,5'), ok(12.5));
assert.deepEqual(en('1,234.56'), ok(1234.56), 'grouped amount in a point locale');
assert.deepEqual(de('€ 1.234,56'), ok(1234.56), 'a currency symbol carries no value');
assert.deepEqual(de('1 234,56'), ok(1234.56), 'space grouping');
assert.deepEqual(de('1 234,56'), ok(1234.56), 'narrow no-break space grouping');
assert.deepEqual(parseCurrency("1'234.56", '.'), ok(1234.56), 'Swiss apostrophe grouping');

// The other notation's decimal separator.
assert.deepEqual(de('12.50'), ok(12.5), '"12.50" in a comma locale is 12.5, never 1250');
assert.deepEqual(de('12.5'), ok(12.5));
assert.deepEqual(en('12,50'), ok(12.5), '"12,50" in a point locale is 12.5');
assert.deepEqual(de('1.234'), ok(1234), 'one foreign separator followed by three digits groups thousands');
assert.deepEqual(de('1.234.567'), ok(1234567));

// Signs.
assert.deepEqual(de('-12,50'), ok(-12.5));
assert.deepEqual(de('−12,50'), ok(-12.5), 'Unicode minus sign');
assert.deepEqual(de('12,50-'), ok(-12.5), 'trailing minus');
assert.deepEqual(de('-0'), ok(0), 'no negative zero');

// Empty means "no amount", never 0.
assert.deepEqual(de(''), ok(null));
assert.deepEqual(de('   '), ok(null));
assert.deepEqual(de('€'), ok(null));

// Ambiguous or broken input is rejected.
assert.deepEqual(de('1,234.56'), rejected, 'decimal separator before a grouping mark');
assert.deepEqual(de('1.2345'), rejected, 'four digits after a single foreign separator');
assert.deepEqual(de('12.345.67'), rejected, 'groups that are not groups of three');
assert.deepEqual(de('1,2,3'), rejected, 'two decimal separators');
assert.deepEqual(de('12abc34'), rejected, 'letters inside the figure');
assert.deepEqual(de('--12'), rejected);
assert.deepEqual(de('1-2'), rejected);

// Numbers pass through.
assert.deepEqual(parseCurrency(12.345, ','), ok(12.345));

// Display (not focused): two decimals, grouped.
assert.equal(formatCurrency(1234.5, ',', '.'), '1.234,50');
assert.equal(formatCurrency(-1234.5, ',', '.'), '-1.234,50');
assert.equal(formatCurrency(-0.001, ',', '.'), '0,00');
assert.equal(formatCurrency(null, ',', '.'), '');
assert.equal(formatCurrency(1234567.891, '.', ','), '1,234,567.89');

// Editing (focused): never rounded, never grouped.
assert.equal(editableCurrency(12.345, ','), '12,345', 'focus must not drop precision');
assert.equal(editableCurrency(12.5, ','), '12,50');
assert.equal(editableCurrency(1234.5, '.'), '1234.50');
assert.equal(editableCurrency(null, ','), '');

// Focus + blur round trip keeps the value.
for (const value of [12.345, 0.1, 1234567.891, -3.14159]) {
    assert.deepEqual(de(editableCurrency(value, ',')), ok(value), `round trip of ${value}`);
}
