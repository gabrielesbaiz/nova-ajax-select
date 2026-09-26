<p align="center">
    <img src="art/nova-ajax-select-logo.png" alt="NovaAjax Select" width="600">
</p>

# NovaAjax Select

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

Nova already has dependent fields. `dependsOn()` re-renders a field whenever
another one changes, and inside that callback you can set `options()` from the
new parent value. If your option sets are small and already in memory — a status
list, twenty categories — **use it, and skip this package.** This one exists for
the options that are too many to send, or that do not live in your database at
all.

- **The option set never has to fit in the form payload.** Eight thousand cities are not serialized into the page on the chance somebody opens the dropdown.
- **Options can come from anywhere.** A closure, an Eloquent model, a relation, a backed enum, a cached HTTP call.
- **Search runs in SQL**, across the columns you name, not in the browser over a list it was handed.
- **It renders a label on index and detail**, so the duplicate `Text` field goes away with it.
- **It refuses values that were never selectable**, with a single membership query rather than a materialized `Rule::in`.

It is built **on top of** `dependsOn()` rather than beside it, so `hide()`,
`show()`, your own `dependsOn()` callbacks and action modals all keep working —
which the 1.x field could not manage.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Nova 5, licensed separately

## Installation

```bash
composer require gabrielesbaiz/nova-ajax-select

php artisan vendor:publish --tag=nova-ajax-select-config
php artisan vendor:publish --tag=nova-ajax-select-lang
```

The service provider is auto-discovered and the compiled field ships with the
package: no migrations, no tables, no assets to build. Both publish steps are
optional — the defaults work untouched.

**[Full installation guide →](https://gabrielesbaiz.github.io/nova-ajax-select/#/install)**

## Documentation

| | |
|---|---|
| [Documentation site](https://gabrielesbaiz.github.io/nova-ajax-select/) | Everything: install, configure, operate. |
| [Guide](https://gabrielesbaiz.github.io/nova-ajax-select/#/guide) | Every feature, in the order you need it. |
| [API reference](https://gabrielesbaiz.github.io/nova-ajax-select/#/api) | Every method, without the prose. |
| [Upgrading from 1.x](https://gabrielesbaiz.github.io/nova-ajax-select/#/upgrade) | Read before you start. |
| [SECURITY.md](SECURITY.md) &middot; [CONTRIBUTING.md](CONTRIBUTING.md) | The threat model, and how to build. |
| [CHANGELOG.md](CHANGELOG.md) | What changed, and when. |

## Testing

```bash
composer test        # Pest
composer format      # Pint
npm test             # Vitest
npm run build        # compile dist/
```

There is no CI. Those four commands are the contract.

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
