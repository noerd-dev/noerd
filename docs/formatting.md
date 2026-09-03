# Currency, Numbers & Dates

Noerd formats every amount, number, date and time through ICU — never through a hard-coded
`number_format($x, 2, ',', '.')` or `->format('d.m.Y')`. Three settings decide what a value
looks like:

| Setting | Where | What it controls |
|---|---|---|
| **Tenant currency** | Setup → System Settings → Currency (`noerd_settings.currency`) | THE currency of every amount in the system. A tenant that selects USD invoices, books and reports in US dollars |
| **Tenant locale** | Setup → System Settings → Locale (`noerd_settings.locale`) | How **documents** are written: PDFs, receipts, customer e-mails — everything that leaves the system. `en-US` prints `$1,234.56` and `09/03/2026`, no matter who generated the document |
| **User locale** | Profile → Locale (`noerd_user_settings.format_locale`) | How the **backend UI** is written for that user: lists, details, dashboards, exports. A German reader sees `1.234,56 $` and `03.09.2026` for the same USD tenant |

The **language** (Setup → Languages, chosen per user in Profile → Language) is a separate setting:
it selects the translation strings and the translatable fields, not the number format. Language
`de` with locale `en-US` is a perfectly valid combination — German labels, US formats.

## Supported locales

The locale list is fixed in the core (`Noerd\Support\Locales::SUPPORTED`) and cannot be extended by a
tenant or a project — every entry needs ICU data and a Carbon translation set:

`de-DE`, `de-AT`, `de-CH`, `en-US`, `en-GB`, `en-IE`, `fr-FR`, `fr-CH`, `it-IT`, `it-CH`, `nl-NL`,
`nl-BE`, `es-ES`, `pt-PT`, `pl-PL`, `cs-CZ`, `da-DK`, `sv-SE`, `nb-NO`, `fi-FI`

`Locales::defaultFor('de')` maps a bare language to its default locale (`de-DE`, `en-US`, …),
`Locales::options()` produces the picker labels
(`Deutsch (Deutschland) · 1.234,56 · 31.12.2026`).

## Resolution order

```text
FormatHelper::locale()          user format_locale → FormatHelper::tenantLocale()
FormatHelper::tenantLocale()    noerd_settings.locale → config('noerd.format.locale') → Locales::defaultFor(App::getLocale())
CurrencyHelper::codeForTenant() noerd_settings.currency → config('noerd.currency.default') → EUR
```

Both settings rows are memoized per request (`NoerdSettings::forTenant()`); a save invalidates the
memo, `FormatHelper::clearCache()` / `CurrencyHelper::clearCache()` flush explicitly.

A host may still **pin** a format for the whole installation through `config('noerd.format.*')`
(`date`, `datetime`, `decimal_separator`, `thousands_separator`, `NOERD_FORMAT_*`). A pinned format
always wins over the locale — leave the keys unset to let the locale decide.

## API

### `Noerd\Helpers\CurrencyHelper`

| Method | Use it for |
|---|---|
| `format(float $value, ?int $tenantId = null, ?string $locale = null)` | Amounts in the **backend UI** — tenant currency, reader's locale |
| `formatForDocument(float $value, ?int $tenantId = null)` | Amounts on **documents** (PDF, receipt, customer e-mail) — tenant currency, tenant locale |
| `formatIn(float $value, string $currency, ?string $locale = null)` | An amount in an explicit currency (e.g. the stored currency of an imported bank transaction) |
| `codeForTenant(?int $tenantId = null)` | The ISO code — for payment payloads, exports, APIs |
| `configForTenant(?int $tenantId = null, ?string $locale = null)` | Code, symbol, separators and symbol position for the reader's locale (consumed by the `input-currency` field) |
| `options()` | The tenant setting's select options with a sample per currency |
| `CURRENCIES` | The supported currencies (`EUR`, `USD`, `GBP`, `CHF`, `CZK`, `DKK`) |
| `clearCache()` | Drop the memoized currency resolution (tests, after a settings save) |

### `Noerd\Helpers\FormatHelper`

| Method | Use it for |
|---|---|
| `date($value)`, `dateTime($value)`, `time($value)` | Dates and times in the **backend UI** (`03.09.2026`, `09/03/2026 2:05 PM`) |
| `documentDate($value, ?int $tenantId)`, `documentDateTime(...)` | Dates on **documents** — tenant locale |
| `decimal($value, $decimals = 2)` | Plain amounts without a symbol (CSV cells) |
| `documentDecimal($value, $decimals, ?int $tenantId)` | The same on documents |
| `number($value, $maxDecimals = 2)` | Quantities — trailing zeros dropped |
| `percent($value, $decimals = 0)` | Percentages (`19 %` / `19%`) |
| `locale()`, `tenantLocale()` | The resolved locales, e.g. to pass into `Carbon::locale()` |
| `csvDelimiter()` | The CSV column separator (`config('noerd.format.csv_delimiter')`, default `;`) |
| `numberSymbols(string $locale)` | The ICU decimal and grouping separators of a locale (`['decimal' => ',', 'group' => '.']`) — for client-side formatters |
| `clearCache()` | Drop the memoized locale resolution (tests, after a settings save) |

Every method accepts an explicit `$locale` as its last parameter when the reader is known.

### `Noerd\Support\Locales`

| Member | Use it for |
|---|---|
| `SUPPORTED` | The closed list of locale codes; a project or tenant cannot extend it |
| `DEFAULT` | The fallback locale (`en-US`) when nothing is configured |
| `isSupported(?string $locale)` | Whether a code is on the list (`null` and unknown codes are `false`) |
| `normalize(string $locale)` | Canonical casing of a tag (`de_de`, `de-de` → `de-DE`); a code without a region is only lower-cased — use `defaultFor()` to reach a supported locale |
| `defaultFor(?string $language)` | The locale a bare language code maps to (`de` → `de-DE`) |
| `label(string $locale, ?string $displayLanguage = null)` | The picker label with a sample (`Deutsch (Deutschland) · 1.234,56 · 31.12.2026`) |
| `sample(string $locale)` | Just the sample part of the label |
| `options(?string $displayLanguage = null)` | `locale => label` for the locale pickers |

## Rules for module code

| Where | Money | Dates / numbers |
|---|---|---|
| PDFs, receipts, customer e-mails, documents | `CurrencyHelper::formatForDocument($x, $model->tenant_id)` | `FormatHelper::documentDate($d, $model->tenant_id)` |
| Backend Livewire views, lists, details, dashboards, relation `titleResolver`s | `CurrencyHelper::format($x)` | `FormatHelper::date()`, `dateTime()`, `decimal()`, `percent()` |
| Public frontends (shop, booking widget) — no noerd user is logged in | `CurrencyHelper::format($x, $tenantId)` with the tenant id passed explicitly | `FormatHelper::date($d, FormatHelper::tenantLocale($tenantId))` |
| Payment payloads, DATEV, ESC/POS, JSON APIs | `'currency' => CurrencyHelper::codeForTenant($tenantId)`; amounts stay machine-formatted (`number_format($x, 2, '.', '')`) | — |

- Never hard-code a currency symbol, `'EUR'`, `d.m.Y` or `Number::currency(..., in: 'EUR', locale: 'de')`
  in module code, templates or translation keys (`__('Buy for :price')`, not `__('Buy for :price €')`).
- List columns of `type: currency`, `date` and `datetime` and detail fields of `type: currency` are
  formatted by the core in the reader's locale — nothing to do in the component.
- The `input-currency` field shows the symbol and separators of the reader's locale and accepts the
  typed value in that notation; the bound value stays a plain decimal.

## Configuration

```php
'currency' => [
    'default' => env('NOERD_CURRENCY', 'EUR'),      // installation-wide default currency
],
'format' => [
    'locale' => env('NOERD_FORMAT_LOCALE'),          // installation-wide fallback locale
    'date' => env('NOERD_FORMAT_DATE'),              // pins, normally unset
    'datetime' => env('NOERD_FORMAT_DATETIME'),
    'decimal_separator' => env('NOERD_FORMAT_DECIMAL_SEPARATOR'),
    'thousands_separator' => env('NOERD_FORMAT_THOUSANDS_SEPARATOR'),
    'csv_delimiter' => env('NOERD_CSV_DELIMITER', ';'),
],
```

`noerd:install` publishes the config; `noerd:update` refreshes it. The two locale columns arrive
with the package migrations — an existing installation runs `php artisan migrate` after upgrading
(`noerd:update` does not migrate).

## Testing

ICU separates symbols and groups with non-breaking spaces; normalise them before asserting
(`zzNormalizeSpaces()` in the noerd test helpers). Prove the mechanics, not the shipped YAML:
create a `NoerdSettings` row with `currency` and `locale`, set the acting user's `format_locale`,
and assert a document renders in the tenant locale while a list renders in the user locale
(`tests/Helpers/CurrencyHelperTest.php`, `tests/Helpers/FormatHelperTest.php`).

## Related

- [Languages](languages.md) — interface languages and translatable values
- [Settings Pages](settings-page.md) — the System Settings page that holds the tenant settings
- [Field Types](field-types.md) — the `currency` field
- [List View](list-view.md) — the `currency`, `date` and `datetime` column types
