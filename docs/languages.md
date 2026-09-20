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

A language selects translation strings and translatable fields; it is NOT a number format. How
amounts, numbers and dates are written is the **locale** (fixed list in the core, per tenant for
documents, per user for the backend UI) — German labels with `en-US` formats is a valid combination.
See [Currency, Numbers & Dates](formatting.md).

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

Put that file in **your own project** as `lang/da.json`. Laravel merges the project's
`lang/{code}.json` *last*, so it overrides whatever noerd and the modules ship (each package
registers its `resources/lang/de.json` via `loadJsonTranslationsFrom()`) — corrections to existing
German wording go into the project's `lang/de.json` the same way, never into a module. An
untranslated key falls back to the English source text.

The `de.json` files shipped with noerd and the modules are the complete list of translatable keys:

```bash
cat vendor/noerd/*/resources/lang/de.json | grep -o '"[^"]*":' | sort -u
```

### Step 3 — Laravel's own messages (optional)

Validation errors and pagination come from Laravel itself and are read from the host application's
`lang/` directory — the noerd package ships no `lang/` folder of its own. Add `lang/da/validation.php`
(copy `lang/en/validation.php`, published by `php artisan lang:publish`, or take the community
translation from [Laravel Lang](https://github.com/Laravel-Lang/lang)).

### Step 4 — Pick the language

Every user selects their own interface language under **Profile → Language**. An
administrator can also set it for someone else in the user detail screen. The choice is
stored per user (`noerd_user_settings.locale`, exposed as `NoerdUser::$locale`) and applied on
every request — including Livewire updates — by the `Noerd\Middleware\SetUserLocale`
middleware, which noerd pushes onto the global `web` group (see [Authentication](auth.md)).

---

## 2. Content languages in Setup Collections

The same languages drive the translatable values of [Setup Collections](setup-collections.md).
Every active language offers a slot in each translatable field; the **language switcher** at the
top of the collection list and detail (`noerd::setup-language-switcher`) selects which slot is
edited (stored in the session, read via `SetupLanguage::selectedCode()`). Existing records simply
have no value for a newly added language yet — they fall back to the default language until
someone fills them in.

### Recognising a translatable field

In a form a translatable input has a light blue frame and a language icon on its label; in a list
the cell has a subtle blue background. The marker follows the field type in every theme and needs no
configuration.

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
