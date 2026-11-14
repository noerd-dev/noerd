/**
 * The arithmetic of the currency field (noerdCurrency in noerd.js). Pure
 * functions, proven by tests/js/currency.mjs without a browser.
 *
 * The field binds a plain number (or null) and shows it with the tenant's
 * separators. The reader may type either notation; what cannot be read
 * unambiguously is REJECTED instead of guessed — a guessed amount is saved
 * without anybody noticing, a rejected one is marked and keeps the old value.
 */

const MINUS_SIGNS = /[−‒–﹣－]/g;
const GROUPING_MARKS = /[\s  '’]/g;

/**
 * @param {*} value     what the reader typed (a number passes through)
 * @param {string} decSep   the locale's decimal separator
 * @returns {{valid: boolean, value: number|null}} `value` is null for an empty input
 */
export function parseCurrency(value, decSep = ',') {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? { valid: true, value } : { valid: false, value: null };
    }

    let text = String(value ?? '').replace(MINUS_SIGNS, '-').trim();

    // Currency symbols, codes and other text around the figure carry no value.
    text = text.replace(/^[^0-9.,\-]+|[^0-9.,\-]+$/g, '').trim();

    if (text === '') {
        return { valid: true, value: null };
    }

    let negative = false;

    if (text.startsWith('-')) {
        negative = true;
        text = text.slice(1).trim();
    } else if (text.endsWith('-')) {
        // Accounting notation: "12,50-".
        negative = true;
        text = text.slice(0, -1).trim();
    }

    text = text.replace(GROUPING_MARKS, '');

    if (text === '' || ! /^[0-9.,]+$/.test(text) || ! /[0-9]/.test(text)) {
        return { valid: false, value: null };
    }

    const parsed = splitNumber(text, decSep);

    if (parsed === null) {
        return { valid: false, value: null };
    }

    const number = Number(parsed.integer + (parsed.fraction !== '' ? '.' + parsed.fraction : ''));

    if (! Number.isFinite(number)) {
        return { valid: false, value: null };
    }

    return { valid: true, value: negative && number !== 0 ? -number : number };
}

/**
 * Integer and fraction digits of a figure made of digits, "." and ",", or null
 * when it cannot be read without guessing.
 *
 * @returns {{integer: string, fraction: string}|null}
 */
function splitNumber(text, decSep) {
    const separators = ['.', ','];
    const decimal = separators.includes(decSep) ? decSep : '.';
    const foreign = decimal === '.' ? ',' : '.';

    const decimalCount = text.split(decimal).length - 1;

    if (decimalCount > 1) {
        return null;
    }

    if (decimalCount === 1) {
        const [integerPart, fraction] = text.split(decimal);

        // A grouping mark after the decimal separator ("1,23.4") is a typo.
        if (fraction.includes(foreign)) {
            return null;
        }

        const integer = ungroup(integerPart, foreign);

        return integer === null ? null : { integer: integer || '0', fraction };
    }

    const groups = text.split(foreign);

    if (groups.length === 1) {
        return { integer: text, fraction: '' };
    }

    // ONE foreign separator followed by one or two digits is the other
    // notation's decimal separator: "12.50" in a comma locale is 12.5, never 1250.
    if (groups.length === 2 && groups[1].length >= 1 && groups[1].length <= 2) {
        return { integer: groups[0] || '0', fraction: groups[1] };
    }

    const integer = ungroup(text, foreign);

    return integer === null ? null : { integer, fraction: '' };
}

/**
 * Digits of an integer part written with thousands groups ("1.234.567"), or
 * null when the groups are not groups of three.
 */
function ungroup(text, mark) {
    if (! text.includes(mark)) {
        return text;
    }

    const groups = text.split(mark);

    if (groups[0] === '' || groups[0].length > 3 || groups.slice(1).some((group) => group.length !== 3)) {
        return null;
    }

    return groups.join('');
}

/**
 * The amount as the field shows it while it is NOT focused: two decimals,
 * grouped with the tenant's separators. Display only — the bound value keeps
 * its precision.
 *
 * @param {number|null} value
 */
export function formatCurrency(value, decSep = ',', thousSep = '.') {
    if (value === null || value === undefined || Number.isNaN(value)) return '';

    const [integer, fraction] = Math.abs(value).toFixed(2).split('.');
    const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, thousSep);
    const sign = value < 0 && Number(integer + '.' + fraction) !== 0 ? '-' : '';

    return sign + grouped + decSep + fraction;
}

/**
 * The amount as the field shows it while the reader edits it: at least two
 * decimals, but never fewer than the value holds, and no grouping — focusing
 * and leaving the field must not round 12.345 to 12.35.
 *
 * @param {number|null} value
 */
export function editableCurrency(value, decSep = ',') {
    if (value === null || value === undefined || Number.isNaN(value)) return '';

    const text = Number(value).toLocaleString('en-US', {
        useGrouping: false,
        minimumFractionDigits: 2,
        maximumFractionDigits: 20,
    });

    return text.replace('.', decSep);
}
