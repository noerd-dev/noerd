# Computed Columns

A computed column shows a value that is **not stored** — the last order date of a customer, the
number of open invoices. Instead of naming an attribute, the list column or detail field names a
**method of the model**:

```yaml
# lists/customers-list.yml
columns:
  - field: name
    label: Name
  - field: last_order
    label: Last order
    type: date
    method: lastOrder
```

```yaml
# details/customer-detail.yml
fields:
  - name: detailData.last_order
    label: Last order
    type: date
    method: lastOrder
```

```php
use Carbon\CarbonInterface;
use Noerd\Attributes\ComputedValue;

class Customer extends Model
{
    #[ComputedValue(with: ['orders'])]
    public function lastOrder(): ?CarbonInterface
    {
        return $this->orders->max('created_at');
    }
}
```

- `field` (list) or `name` (detail) names the value. In a list it must not be a table column; the
  value is exposed under that name on every row.
- `type` formats the result like any other column or field: `date`, `datetime`, `number`,
  `currency`, `bool`, or text when omitted.
- `method` names the model method that computes it.

## The Marker

Only a method that carries `#[Noerd\Attributes\ComputedValue]` is called. It must also be public
and non-static, and take no required parameters. This is checked by reflection before the call.

Anything else renders empty and is logged as `Computed column method not allowed`. That covers a
typo, an unmarked method, `delete()` and `save()`, so a YAML entry can never trigger a side
effect.

`with:` names the relations the method reads. A list eager-loads them once per page, so the method
does not issue one query per row. A detail loads them with `loadMissing()`.

## Return Values

The method may return a scalar, a `DateTimeInterface`, a `BackedEnum` (its value) or a
`Stringable`. The column `type` decides the presentation:

| `type` | Shown as |
|--------|----------|
| `date` / `datetime` | the date (`Y-m-d` / `Y-m-d H:i:s`, then formatted in the reader's locale) |
| `number` / `currency` | a number |
| `bool` | Yes/No |
| anything else | the value as text |

Any other return value, such as a model or an array, is blank. An exception thrown by the method
renders the value blank and is logged as `Computed column method failed`, so it never breaks a
list or a form.

## In Lists

- The values are computed once per page for all rows, and once per chunk in the CSV export.
- A method runs in PHP after the page has been loaded, so a method column is **display only**. It
  is added to `notSortableColumns`, gets no filter funnel and is never searched.

## In Detail Forms

- The value is computed for the saved record before every render, so it is fresh after a save. A
  new record shows the field empty.
- The value lives in the component property `$computedValues`, never in `$detailData`. The field is
  re-bound to `computedValues.{key}`, so no store path can persist it — neither the default one
  nor a custom `store()` — and `required:` is ignored.
- It always renders as text in the `display` theme, formatted by the field `type`.

## Further Providers

`method:` is computed by `Noerd\Support\ModelMethodColumns`, the provider the core registers on
`Noerd\Services\ComputedColumnRegistry`. A package can register a provider of its own that
recognises another YAML key. That provider may also make its columns sortable and filterable,
e.g. through subselects. See
[Extension Registries](extension-registries.md#computedcolumnregistry).
