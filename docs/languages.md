# Languages

Noerd manages its languages per tenant in **Setup → Languages** (table `setup_languages`, model
`Noerd\Models\SetupLanguage`). One list of languages serves two purposes:

| | Interface language | Content language (Setup Collections) |
|---|---|---|
| **What it controls** | The language of the backend UI — menus, buttons, labels | The language of translatable values in [Setup Collections](setup-collections.md) (country names, …) |
| **Chosen by** | Each user, in their profile (`Profile → Language`) | The language switcher at the top of the collection screens |
| **Resolved via** | `NoerdUser::$locale`, applied by the `SetUserLocale` middleware | `SetupLanguage::selectedCode()` (session choice, else the tenant default) |

Languages can be extended with any language you like — **entirely from your own project,
without a single change to the Noerd framework or its modules**. The optional CMS module ships its
own, independent language management for its content; see its documentation.

## Language vs. locale

A language is not a number format. Noerd keeps the two apart:

| | Language | Locale |
|---|---|---|
| **Decides** | Translation strings, translatable fields | How `1234.56`, the 3rd of September and an amount are written |
| **List** | Per tenant, extensible (Setup → Languages) | Fixed in the core (`Noerd\Support\Locales::SUPPORTED`, e.g. `de-DE`, `en-US`) |
| **Per user** | Profile → Language | Profile → Locale (backend UI: lists, details, dashboards) |
| **Per tenant** | Default language for new users | Setup → System Settings → Locale (documents: PDFs, receipts, customer e-mails) |

A user may combine German as the language with `en-US` as the locale and gets German labels with
`$1,234.56` and `09/03/2026`. Everything about locales and the currency lives in
[Currency, Numbers & Dates](formatting.md).

---

## 1. Adding an interface language (e.g. Danish)

### Step 1 — Create the language

Go to **Setup → Languages → New Language** and fill in:

| Field | Value |
|---|---|
| Code | `da` — the [ISO 639-1](https://en.wikipedia.org/wiki/List_of_ISO_639_language_codes) code, lowercase |
| Name | `Dansk` — the name shown in the picker |
| Active | ✅ |
| Default | Leave unticked unless Danish should be the default for new users |
| Sort Order | Position in the picker |

The language is per tenant. It appears immediately in the language picker of every
user profile and in the user detail screen — no deployment needed. A new tenant starts with a
default language set (`SetupLanguage::ensureDefaultLanguagesForTenant()`), and
`SetupLanguage::active()` / `activeCodes()` / `defaultLanguage()` / `defaultCode()` expose the tenant's
languages to your own code.

### Step 2 — Provide the translations

Noerd uses **the English text itself as the translation key**. A file therefore maps
English → Danish:

```json
{
    "Save": "Gem",
    "Cancel": "Annuller",
    "New Customer": "Ny kunde",
    "Are you sure you want to delete this entry?": "Er du sikker på, at du vil slette denne post?"
}
```

Put that file in **your own project**, at:

```text
lang/da.json
```

> **This is the important part:** Laravel merges the project's `lang/{code}.json`
> *last*, so it overrides whatever noerd and the modules ship (each package registers its
> `resources/lang/de.json` via `loadJsonTranslationsFrom()`). You never have to edit a module
> or send a pull request to Noerd — everything can live in your own repository,
> including corrections to existing German or English wording (`lang/de.json`).

Any key you do not translate falls back to the English source text, so you can start
with the 50 strings your users see most and grow the file over time.

### Step 3 — Laravel's own messages (optional)

Validation errors, date formats and pagination come from Laravel itself and are read from
the host application's `lang/` directory — the noerd package ships no `lang/` folder of its
own. To translate them, add:

```text
lang/da/validation.php
```

Copy your application's `lang/en/validation.php` as a starting point (`php artisan lang:publish`
publishes it), or fetch the community translation for your language from
[Laravel Lang](https://github.com/Laravel-Lang/lang).

### Step 4 — Pick the language

Every user selects their own interface language under **Profile → Language**. An
administrator can also set it for someone else in the user detail screen. The choice is
stored per user (`noerd_user_settings.locale`, exposed as `NoerdUser::$locale`) and applied on
every request — including Livewire updates — by the `Noerd\Middleware\SetUserLocale`
middleware, which noerd pushes onto the global `web` group (see [Authentication](auth.md)).

### Finding the strings to translate

The English keys are the texts you see in the UI. To collect them systematically, the
`de.json` files that ship with noerd and the modules are the complete list of translatable
strings:

```bash
cat vendor/noerd/*/resources/lang/de.json | grep -o '"[^"]*":' | sort -u
```

Take the left-hand side (the English key) and write your Danish value for it in
`lang/da.json`.

---

## 2. Content languages in Setup Collections

The same languages drive the translatable values of [Setup Collections](setup-collections.md).
Every active language offers a slot in each translatable field; the **language switcher** at the
top of the collection list and detail (`noerd::setup-language-switcher`) selects which slot is
edited (stored in the session, read via `SetupLanguage::selectedCode()`). Existing records simply
have no value for a newly added language yet — they fall back to the default language until
someone fills them in.

### Recognising a translatable field

- **In a form**, the input has a **light blue frame** and the label carries a small
  language icon. Hovering the icon explains that the value belongs to the language
  currently selected in the switcher. Switch the language and the value changes.
- **In a list**, the cell has a **subtle blue background**. A plain cell (e.g. a country
  code) holds the same value in every language.

The marker works in every theme (`default`, `compact`, `numbered`) and needs no
configuration — it follows the field type.

---

## Making a field translatable

Whether a field is translatable is decided by its **field type** in the YAML config
(registered core types, see [Field Types](field-types.md)):

```yaml
fields:
  - name: detailData.name
    label: Country Name
    type: translatableText      # ← per-language value
    colspan: 8
  - name: detailData.code
    label: Country Code
    type: text                  # ← one value for all languages
    colspan: 4
```

| Type | Renders as |
|---|---|
| `translatableText` | Single-line input, one value per language |
| `translatableTextarea` | Multi-line text, one value per language |
| `translatableRichText` | Rich text editor, one value per language |

The stored value becomes a map keyed by the language code:

```json
{ "name": { "de": "Deutschland", "en": "Germany", "da": "Tyskland" } }
```

Adding a language never migrates this data — the new key simply appears as soon as
someone saves a value for it.

For a list column, add `translatable: true` so the cell gets the blue background:

```yaml
columns:
  - field: name
    label: Country Name
    translatable: true
  - field: code
    label: Country Code
```

(For Setup Collections this is derived from the field type automatically — no extra
configuration needed.)

---

## Removing a language

Deactivate it (untick **Active**) rather than deleting it. Deactivating hides it from
the switchers and pickers while the already translated values stay in the database, so
you can bring it back at any time. Deleting the language record leaves the stored
values in place too, but they are no longer reachable through the UI.

The default language cannot be left empty: if you delete or deactivate it, the next
active language automatically becomes the default.

---

## Related

- [Field Types](field-types.md) — all available field types
- [Themes](themes.md) — how form elements are styled per theme
- [Setup Collections](setup-collections.md) — tenant-maintained lookup data (countries, …)
