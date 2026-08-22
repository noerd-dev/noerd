# Brand (Colors & Branding)

The **brand** is the application's color palette plus its branding assets (logo, auth background
image). It controls the sidebar, appbar, page background and button colors through a set of
`brand-*` CSS custom properties, served by `Noerd\Services\BrandService`.

Brand and theme are two orthogonal concepts:

- **Brand** (`noerd.brand`, `NOERD_BRAND`): the COLOR PALETTE, documented here.
- **Theme** (`noerd.theme`, `NOERD_THEME`): the FORM LAYOUT system — see [Themes](themes.md).

## Selecting a Brand

```env
NOERD_BRAND=sand
```

`noerd.brand.active` selects one of the presets defined under `noerd.brand.presets`. Noerd ships
three presets: `default`, `sand` and `white`. An unknown brand name falls back to the `default`
preset.

## Color Keys

Every preset defines the same 10 color keys. Each key becomes a CSS custom property
`--color-{key}` (e.g. `brand-primary` → `--color-brand-primary`), which backs the Tailwind
utilities `bg-brand-primary`, `text-brand-primary-text`, `border-brand-border`, and so on.

| Key | Colors |
|-----|--------|
| `brand-bg` | Page background (the `<body>`) |
| `brand-navi` | Sidebar navigation background |
| `brand-navi-hover` | Sidebar navigation item hover state |
| `brand-primary` | Primary buttons and accents |
| `brand-primary-text` | Text on primary surfaces |
| `brand-secondary` | Secondary buttons |
| `brand-secondary-text` | Text on secondary surfaces |
| `brand-danger` | Danger buttons (e.g. delete) |
| `brand-danger-text` | Text on danger surfaces |
| `brand-border` | Emphasized borders (e.g. primary button outline) |

## Per-Color Overrides

Every color key can be overridden individually via an environment variable — on top of whichever
preset is active:

| Color key | Environment variable |
|-----------|----------------------|
| `brand-bg` | `NOERD_COLOR_BRAND_BG` |
| `brand-navi` | `NOERD_COLOR_BRAND_NAVI` |
| `brand-navi-hover` | `NOERD_COLOR_BRAND_NAVI_HOVER` |
| `brand-primary` | `NOERD_COLOR_BRAND_PRIMARY` |
| `brand-primary-text` | `NOERD_COLOR_BRAND_PRIMARY_TEXT` |
| `brand-secondary` | `NOERD_COLOR_BRAND_SECONDARY` |
| `brand-secondary-text` | `NOERD_COLOR_BRAND_SECONDARY_TEXT` |
| `brand-danger` | `NOERD_COLOR_BRAND_DANGER` |
| `brand-danger-text` | `NOERD_COLOR_BRAND_DANGER_TEXT` |
| `brand-border` | `NOERD_COLOR_BRAND_BORDER` |

```env
NOERD_BRAND=sand
NOERD_COLOR_BRAND_PRIMARY=#1d4ed8
NOERD_COLOR_BRAND_PRIMARY_TEXT=#ffffff
```

## How the Colors Are Resolved and Emitted

`Noerd\Services\BrandService` (registered as a singleton) resolves the palette:

1. Read the active preset from `noerd.brand.presets.{active}` (missing preset → `default` preset).
2. For each color key, apply the value from `noerd.brand.overrides` when it is non-empty.

```php
use Noerd\Services\BrandService;

app(BrandService::class)->colors();                        // ['brand-bg' => '#f9f9f9', …]
app(BrandService::class)->color('brand-primary');          // '#000'
app(BrandService::class)->cssCustomProperties();           // '--color-brand-bg: #f9f9f9; …'
```

The package's own stylesheet (`resources/css/noerd.css`, imported into `resources/css/app.css` by
`noerd:install`) declares every color key as a `--color-brand-*` custom property in its `@theme`
block. That block exists purely to **register** the Tailwind utilities: `bg-brand-primary` compiles
to `background-color: var(--color-brand-primary)`, never to a literal value.

`cssCustomProperties()` is emitted by the app layout
(`app-modules/noerd/resources/views/layouts/app.blade.php`) inside the `:root { … }` style block and
re-declares the same custom properties, so the resolved values take effect at runtime. The effective
colors therefore always come from the config, and changing `NOERD_BRAND` or an override needs **no
CSS rebuild**.

> Do not reintroduce the palette as literal values in a `tailwind.config.js` `theme.extend.colors`
> block. Tailwind would then compile the utilities to fixed hex values and `BrandService` could no
> longer override them. Earlier noerd versions generated exactly such a config plus a `@config` line
> in `app.css`; `noerd:update` offers to remove both.

## Branding Assets

| Config key | Env | Description |
|------------|-----|-------------|
| `noerd.branding.logo` | `NOERD_LOGO` | Logo URL/path rendered by `<x-noerd::application-logo>` on the auth screens (login, forgot password, reset password). Empty (the default) renders no logo. |
| `noerd.branding.favicon` | `NOERD_FAVICON` | Favicon URL/path emitted as the `<link rel="icon">` tag. Empty (the default) keeps the browser default. |
| `noerd.branding.auth_background_image` | `NOERD_AUTH_BACKGROUND_IMAGE` | Full-cover image on the illustration half of the auth screens. Empty (the default) renders no image. |

## Sidebar Dimensions

| Config key | Env | Default |
|------------|-----|---------|
| `noerd.sidebar.apps_width` | `NOERD_SIDEBAR_APPS_WIDTH` | `80px` |
| `noerd.sidebar.navigation_width` | `NOERD_SIDEBAR_NAVIGATION_WIDTH` | `280px` |

The app layout emits them as the CSS custom properties `--sidebar-apps-width` and
`--sidebar-nav-width` (plus the derived `--sidebar-total-width`), which the appbar, the sidebar
navigation, the topbar and the content offsets all consume. The navigation width is the *initial*
width: a user-resized sidebar is persisted in the session (`sidebar_nav_width`) and wins over the
config value for that session.

## Custom Palette in a Project

Two options, both in the project (never in the noerd module):

**1. Individual env overrides** — keep a preset and re-color single keys via the
`NOERD_COLOR_BRAND_*` variables shown above. Right for "our primary color on the standard look".

**2. A full custom preset** — add a preset to the project's `config/noerd.php` and activate it:

```php
'brand' => [
    'active' => env('NOERD_BRAND', 'acme'),

    'presets' => [
        'acme' => [
            'brand-bg'             => '#f8fafc',
            'brand-navi'           => '#0f172a',
            'brand-navi-hover'     => '#1e293b',
            'brand-primary'        => '#1d4ed8',
            'brand-primary-text'   => '#ffffff',
            'brand-secondary'      => '#ffffff',
            'brand-secondary-text' => '#374151',
            'brand-danger'         => '#fecaca',
            'brand-danger-text'    => '#374151',
            'brand-border'         => '#1d4ed8',
        ],
    ],

    'overrides' => [
        // keep the NOERD_COLOR_BRAND_* entries so env overrides still work
    ],
],
```

**Important:**

- A custom preset must define **all 10 keys** — `cssCustomProperties()` emits exactly the keys of
  the active preset, and a missing key would leave the static CSS default in place for that color.
- Overrides apply on top of *whichever* preset is active; an empty override value is ignored.
- `BrandService` reads the config on every call — no cache to clear.
- The brand never affects detail form layout; that is the theme system
  (see [Theme vs. Brand](themes.md#theme-vs-brand)).
