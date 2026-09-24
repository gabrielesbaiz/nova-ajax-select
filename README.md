<p align="center">
    <img src="art/nova-ajax-select-logo.png" alt="Nova Ajax Select" width="600">
</p>

# Nova Ajax Select

A Laravel Nova select field whose options are resolved on the server — scoped to whatever the user picked a moment ago, searched in the database rather than the browser, and validated on the way back in.

[![Latest version](https://img.shields.io/packagist/v/gabrielesbaiz/nova-ajax-select.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/nova-ajax-select)
[![PHP](https://img.shields.io/packagist/dependency-v/gabrielesbaiz/nova-ajax-select/php?style=flat-square)](composer.json)
[![Laravel](https://img.shields.io/packagist/dependency-v/gabrielesbaiz/nova-ajax-select/illuminate%2Fsupport?style=flat-square&label=laravel)](composer.json)
[![Downloads](https://img.shields.io/packagist/dt/gabrielesbaiz/nova-ajax-select.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/nova-ajax-select)
[![Stars](https://img.shields.io/github/stars/gabrielesbaiz/nova-ajax-select?style=flat-square&logo=github)](https://github.com/gabrielesbaiz/nova-ajax-select/stargazers)
[![Sponsor](https://img.shields.io/github/sponsors/gabrielesbaiz?style=flat-square&label=sponsor&logo=github)](https://github.com/sponsors/gabrielesbaiz)

### 📖 [Read the documentation →](https://gabrielesbaiz.github.io/nova-ajax-select/)

Every method, a live chain you can click through, the option-shape normalizer
with your own payload pasted in, and the upgrade path from 1.x.

> [!CAUTION]
> **Upgrading from 1.x?** Nothing is required — `get()`, `parent()` and the
> `NovaAjaxSelect` class all still work. But 1.x shared its asset handle with
> `alexwenzel/ajax-select`, and **with both packages installed one of the two
> bundles was never served**. If you have both, remove the other one now. See
> the [upgrade guide](https://gabrielesbaiz.github.io/nova-ajax-select/#/upgrade).

> [!IMPORTANT]
> A ⭐ costs you nothing and helps other developers find this package.
> [Sponsoring](https://github.com/sponsors/gabrielesbaiz) keeps it compatible
> with every new Laravel release.

## What it does

Nova already has dependent fields. `dependsOn()` re-renders a field on the
server whenever another one changes, and inside that callback you can call
`->options()` with whatever the new parent value implies. If your option sets
are small and already in memory — a status list, twenty categories — **use it,
and skip this package.**

This package exists for the case Nova's own machinery handles badly: options
that are **too many to send**, or that **do not live in your database at all**.

- **The option set never has to fit in the form payload.** Eight thousand cities are not serialized into the page on the chance the user opens the dropdown.
- **Options can come from anywhere.** A closure, an Eloquent model, a relation, a backed enum, a cached HTTP call to a geo service.
- **Search runs in SQL**, across the columns you name, not in the browser over a list it was handed.
- **It renders a label on index and detail**, so the duplicate `Text::make(...)->onlyOnDetail()` goes away with it.
- **It refuses values that were never selectable**, with a single membership query rather than a materialized `Rule::in`.

And it is built **on top of** `dependsOn()` rather than beside it, so it keeps
everything Nova's own dependent fields give you — `hide()`, `show()`, your own
`dependsOn()` callbacks, action modals — which the 1.x field did not.

```php
use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;

Select::make('Country', 'country')->options(['ca' => 'Canada', 'gb' => 'United Kingdom', 'jp' => 'Japan']),

AjaxSelect::make('Region', 'region_id')
    ->parent('country')
    ->optionsFromModel(Region::class, query: fn ($query, $context) => $query->where('country', $context->parent())),

AjaxSelect::make('City', 'city_id')
    ->parent('region_id')
    ->optionsFromModel(City::class, query: fn ($query, $context) => $query->where('region_id', $context->parent()))
    ->labelFrom('city.name'),
```

No route to write. No controller. No `{value, display}` mapping. No second
field to show the label on the detail page.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- [Laravel Nova](https://nova.laravel.com) 5 — a paid package, licensed separately

## Installation

```bash
composer require gabrielesbaiz/nova-ajax-select
```

The service provider is auto-discovered. There are no migrations, no tables and
no assets to publish — the compiled field ships with the package. Publishing the
configuration and the translations is optional:

```bash
php artisan vendor:publish --tag=nova-ajax-select-config
php artisan vendor:publish --tag=nova-ajax-select-lang
```

**[Full installation guide →](https://gabrielesbaiz.github.io/nova-ajax-select/#/install)**

## Documentation

| | |
|---|---|
| [Introduction](https://gabrielesbaiz.github.io/nova-ajax-select/#/intro) | What the field is for, and when not to reach for it |
| [Installation](https://gabrielesbaiz.github.io/nova-ajax-select/#/install) | Requirements, install, publishing |
| [Configuration](https://gabrielesbaiz.github.io/nova-ajax-select/#/configuration) | Every key in `config/nova-ajax-select.php` |
| [Guide](https://gabrielesbaiz.github.io/nova-ajax-select/#/guide) | Chains, search, labels, validation, caching |
| [API reference](https://gabrielesbaiz.github.io/nova-ajax-select/#/api) | Every method, and the context an options closure receives |
| [Troubleshooting](https://gabrielesbaiz.github.io/nova-ajax-select/#/troubleshooting) | Symptom, cause, fix |
| [Security](https://gabrielesbaiz.github.io/nova-ajax-select/#/security) | The threat model, and what is deliberate |
| [Upgrading](https://gabrielesbaiz.github.io/nova-ajax-select/#/upgrade) | From 1.x, step by step |
| [Changelog](https://gabrielesbaiz.github.io/nova-ajax-select/#/changelog) | What changed, and when |
| [SECURITY.md](SECURITY.md) · [CONTRIBUTING.md](CONTRIBUTING.md) · [CHANGELOG.md](CHANGELOG.md) | The same, in the repository |

## Testing

```bash
composer test        # Pest — 96 tests against Nova's real sync endpoints
composer format      # Pint
npm test             # Vitest — 34 tests: the option normalizer and the fetch state machine
npm run build        # compile dist/
```

There is no CI. Those four commands are the contract. The PHP suite drives
Nova's own field-sync endpoints rather than mocking them, and it includes a
regression test for the asset-handle collision that made the 1.x field
unreachable.

## Contributing

Thank you for considering contributing. The guide is in
[CONTRIBUTING.md](CONTRIBUTING.md).

## Security vulnerabilities

Please review [SECURITY.md](SECURITY.md) for reporting a vulnerability. Please
do not open a public issue.

## Credits

Written and maintained by [Gabriele Sbaiz](https://github.com/gabrielesbaiz).

The original field was written by [alexwenzel](https://github.com/alexwenzel)
and [dillingham](https://github.com/dillingham); this package began as a fork of
it and owes them the idea. It builds on Laravel and Laravel Nova.

## Support this package

If it is useful to you:

- ⭐ **Star the repo.** Free, thirty seconds, and it is the first signal other developers look at.
- ❤️ **[Become a sponsor](https://github.com/sponsors/gabrielesbaiz).** From $5 a month.
- 🐛 **Open a good issue.** A clear reproduction is worth more than you think.
- 🗣️ **Tell another Laravel developer.** Word of mouth is how packages survive.

[![Sponsor on GitHub](https://img.shields.io/badge/Sponsor-gabrielesbaiz-ff69b4?style=for-the-badge&logo=github-sponsors)](https://github.com/sponsors/gabrielesbaiz)

## Disclaimer

This package is provided **as is**, without warranty of any kind, express or
implied, including but not limited to the warranties of merchantability,
fitness for a particular purpose, title and non-infringement. To the fullest
extent permitted by applicable law, in no event shall the authors, copyright
holders or contributors be liable for any claim, damages or other liability —
whether in an action of contract, tort or otherwise — arising from, out of or in
connection with this package or its use, including without limitation any
direct, indirect, incidental, special, exemplary, consequential or punitive
damages, loss of data, loss of profits, business interruption, or unauthorised
access to or disclosure of information.

This package executes application-supplied closures during authorized Nova
requests and can cache their results. Whoever deploys it is responsible for
what those closures return and who may see it. That responsibility includes,
and is not limited to, scoping option queries to the current user or tenant,
setting `cacheScope()` when cached options are not global, deciding whether
option validation is sufficient for your threat model, and reviewing the code
yourself before putting it in front of data you cannot afford to leak. Nothing
here constitutes security, legal or compliance advice.

Use of this package is entirely at your own risk.

## License

MIT. See [LICENSE.md](LICENSE.md). The MIT licence's warranty disclaimer and
limitation of liability apply in full, alongside the disclaimer above.
