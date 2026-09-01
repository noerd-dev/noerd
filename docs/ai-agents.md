# AI Agents (Laravel Boost guidelines & skills)

Noerd ships the rules for building with the framework in a form that coding agents (Claude Code,
Cursor, Junie, Copilot, …) can read — so a developer who installs `noerd/noerd` and works with an AI
assistant gets the same conventions the noerd core team uses, without copying anything by hand.

## What the package ships

| Location | Purpose |
|----------|---------|
| `resources/boost/guidelines/core.blade.php` | The **Noerd Framework** guideline: the hard rules for lists, details/pages, modals, themes, modules, install/update commands, translations and tests. Rendered into the project's agent files by [Laravel Boost](https://github.com/laravel/boost) |
| `resources/boost/skills/*/SKILL.md` | Task-oriented **skills** — step-by-step procedures with checklists: `noerd-list-development`, `noerd-detail-development`, `noerd-modal-development`, `noerd-module-development`, `noerd-testing` |
| `AGENTS.md` (+ `CLAUDE.md`) | Contributor guide for working *inside* the noerd repository (workflow, tests, code style, architecture guardrails) |
| `docs/` | This documentation — included in the Packagist dist, so the guideline and the skills can point into it (`vendor/noerd/noerd/docs/*.md`) |

Laravel Boost discovers the guideline and the skills automatically in every Composer package that
has a `resources/boost/guidelines/` or `resources/boost/skills/` folder — no service provider
registration is involved.

## Enabling it in a project

1. Install Boost: `composer require laravel/boost --dev` and run `php artisan boost:install`.
   Boost lists `noerd/noerd` under the discovered third-party packages; select it (guideline and
   skills).
2. Alternatively edit `boost.json` by hand (`packages` lists the third-party packages whose guidelines and skills are installed; `skills` is filled by Boost with the installed skill names) and re-run the update:

```json
{
    "agents": ["claude_code"],
    "editors": ["claude_code"],
    "guidelines": true,
    "packages": ["noerd/noerd"],
    "skills": []
}
```

```bash
php artisan boost:update
```

Boost writes the `=== noerd/noerd/core rules ===` block into `CLAUDE.md` (inside the
`<laravel-boost-guidelines>` markers), `.cursor/rules/laravel-boost.mdc`, `.junie/guidelines.md`, …
and copies the skills to `.claude/skills/noerd-*`. Re-run `php artisan boost:update`
after every noerd upgrade so the rendered rules follow the installed version.

Keep your own project rules outside the Boost markers — Boost overwrites only the block between
them.

## Shipping guidelines with your own module

A module created with `php artisan noerd:module` already contains:

- `resources/boost/guidelines/core.blade.php` — a module guideline (purpose, YAML locations,
  component names, install/update commands, test call). It is Blade-rendered by Boost, so literal
  `{{ }}` or `@directive` text must sit inside `@verbatim … @endverbatim`.
- `AGENTS.md` and a `CLAUDE.md` that imports it (`@AGENTS.md`).

A module may additionally ship Claude Code skills in a top-level `skills/{skill-name}/` folder:
`noerd:install-{module}` / `noerd:update-{module}` (`HasModuleInstallation::publishSkills()`) link
or copy every such folder into the project's `.claude/skills/` independently of Boost.

Add `"noerd/{module}"` (the Composer package name) to the `packages` array of the host's
`boost.json` (Boost ≥ 2; `boost:install` offers it interactively) and run `php artisan boost:update`. Skills are optional: create
`resources/boost/skills/{skill-name}/SKILL.md` with a YAML front matter containing at least `name`
(equal to the folder name) and `description`.

## Writing a good guideline

- State rules, not prose: what to do, what never to do, and the reference file/doc page.
- Keep the module guideline to the module — the framework rules already come from `noerd/noerd`.
- Update the guideline (and the skill) in the same change that adds or alters a feature; noerd's
  own suite checks with `tests/Feature/BoostGuidelineTest.php` that the guideline still renders
  and that every skill has a valid front matter.

## Next Steps

- [Creating Modules](creating-modules.md)
- [Testing](testing.md)
