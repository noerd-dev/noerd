# Languages

Noerd knows **two independent language systems**. They are configured in different
places and solve different problems:

| | Interface language | Content language |
|---|---|---|
| **What it controls** | The language of the backend UI — menus, buttons, labels | The language of the *data* your editors maintain (page titles, country names, …) |
| **Configured in** | Setup → Languages | CMS → CMS Languages |
| **Stored in** | `setup_languages` | `cms_languages` |
| **Chosen by** | Each user, in their profile | The language switcher at the top of the record |

Both can be extended with any language you like — **entirely from your own project,
without a single change to the Noerd framework or its modules**.

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
user profile and in the user detail screen — no deployment needed.

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

```
lang/da.json
```

> **This is the important part:** Laravel merges the project's `lang/{code}.json`
> *last*, so it overrides whatever the modules ship. You never have to edit a module
> or send a pull request to Noerd — everything can live in your own repository,
> including corrections to existing German or English wording (`lang/de.json`).

Any key you do not translate falls back to the English source text, so you can start
with the 50 strings your users see most and grow the file over time.

### Step 3 — Laravel's own messages (optional)

Validation errors, date formats and pagination come from Laravel itself. To translate
them, add:

```
lang/da/validation.php
```

Copy `lang/de/validation.php` as a starting point, or fetch the community translation
for your language from [Laravel Lang](https://github.com/Laravel-Lang/lang).

### Step 4 — Pick the language

Every user selects their own interface language under **Profile → Language**. An
administrator can also set it for someone else in the user detail screen. The choice is
stored per user and applied on every request.

### Finding the strings to translate

The English keys are the texts you see in the UI. To collect them systematically, the
`de.json` files that ship with the modules are the complete list of translatable
strings:

```bash
cat app-modules/*/resources/lang/de.json | grep -o '"[^"]*":' | sort -u
```

Take the left-hand side (the English key) and write your Danish value for it in
`lang/da.json`.

---

## 2. Adding a content language (e.g. Danish)

This is what your editors use to maintain the *same record in several languages* —
a page title in German and Danish, a country name in English and Danish.

### Step 1 — Create the language

Go to **CMS → CMS Languages → New Language**:

| Field | Value |
|---|---|
| Code | `da` |
| Name | `Dansk` |
| Active | ✅ |
| Default | The language used as the fallback when a value has not been translated yet |
| Sort Order | Position in the language switcher |

That is the whole setup. From the next page load:

- every translatable field offers a Danish slot,
- the language switcher at the top of the record and above the list offers Danish,
- new page slugs get the `/da/...` prefix (the default language keeps the bare slug).

Existing records simply have no Danish value yet — they fall back to the default
language until someone fills them in. Nothing breaks.

### Step 2 — Translate the content

Switch the language switcher to **Dansk** and edit the records. Only the translatable
fields change with the switcher; everything else (a country code, a price, a checkbox)
is shared across all languages.

### Recognising a translatable field

Translatable fields are marked in two places:

- **In a form**, the input has a **light blue frame** and the label carries a small
  language icon. Hovering the icon explains that the value belongs to the language
  currently selected in the switcher. Switch the language and the value changes.
- **In a list**, the cell has a **subtle blue background**. A plain cell (e.g. a country
  code) holds the same value in every language.

The marker works in every theme (`default`, `compact`, `numbered`) and needs no
configuration — it follows the field type.

---

## Which one do I need?

> *Should a Danish user see Danish buttons, or should the data itself exist in Danish?*

- **Danish buttons** → interface language (Setup → Languages + `lang/da.json`)
- **Danish data** → content language (CMS → CMS Languages)
- **Both** → set up both; they are completely independent and do not have to match.

---

## Making a field translatable

Whether a field is translatable is decided by its **field type** in the YAML config:

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
