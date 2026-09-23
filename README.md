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

```php
Select::make('Province', 'province_id')->options($provinces),

AjaxSelect::make('City', 'city_id')
    ->parent('province_id')
    ->optionsFromModel(City::class, query: fn ($query, $context) => $query->where('province_id', $context->parent()))
    ->labelFrom('city.name'),
```

No route to write. No controller. No `{value, display}` mapping. No second field
to show the label on the detail page.

> [!CAUTION]
> **Upgrading from 1.x?** Nothing is required — `get()`, `parent()` and the
> `NovaAjaxSelect` class all still work. But 1.x shared its asset handle with
> `alexwenzel/ajax-select`, and **with both packages installed one of the two
> bundles was never served**. If you have both, remove the other one now. See
> [Upgrading from 1.x](#upgrading-from-1x).

> [!IMPORTANT]
> A ⭐ costs you nothing and helps other developers find this package.
> [Sponsoring](https://github.com/sponsors/gabrielesbaiz) keeps it compatible
> with every new Laravel release.

## What it does

Nova already has dependent fields. `dependsOn()` re-renders a field on the
server whenever another one changes, and inside that callback you can call
`->options()` with whatever the new parent value implies. If your option sets
are small and already in memory — a status list, twenty categories, the
provinces of one country — **use it, and skip this package.**

This package exists for the case Nova's own machinery handles badly: options
that are **too many to send**, or that **do not live in your database at all**.

- **The option set never has to fit in the form payload.** Eight thousand
  cities are not serialized into the page on the chance the user opens the
  dropdown. The field waits, then asks — and the search runs in SQL, not in the
  browser over a list it was handed.
- **Options can come from anywhere.** A closure, an Eloquent model, a relation,
  a backed enum, a cached HTTP call to a geo service. The field does not care.
- **One line per relationship, not one route per relationship.** Province → city
  → zip code is three fields and no routes. The endpoints you wrote by hand for
  1.x — query, `->map(['value' => .., 'display' => ..])`, cache — are deleted.
- **It renders a label on index and detail.** The duplicate
  `Text::make(...)->onlyOnDetail()` that every ajax-select call site grew goes
  away with it.
- **It refuses values that were never selectable**, with a single membership
  query rather than a materialized `Rule::in`.

And it is built **on top of** `dependsOn()` rather than beside it, so it keeps
everything Nova's own dependent fields give you — which the 1.x field did not.

### What changed from 1.x, and why it matters

1.x found its parent field by walking Vue's internal component tree
(`walk(this.$parent.$.subTree)`, reading `vnode.shapeFlag & 16`). That worked
until it didn't:

| | 1.x | 2.0 |
|---|---|---|
| Finds its parent by | walking Vue internals | Nova's field event bus |
| `dependsOn()` on the same field | breaks — call sites fell back to `canSee()` | works |
| `hide()` / `show()` | ignored | works |
| Inside an action modal | fails to find the parent | works |
| Inside panels, tabs, pivot forms | fragile | works |
| Requests while typing | one per keystroke, never cancelled | debounced and cancelled |
| A failed request | silent | reported, options cleared |
| Options resolved | in the browser, from your route | on the server, in the field |

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Nova 5

## Installation

```bash
composer require gabrielesbaiz/nova-ajax-select
```

The service provider is auto-discovered. There are no migrations, no tables and
no assets to publish — the compiled field ships with the package.

Publishing is optional:

```bash
php artisan vendor:publish --tag=nova-ajax-select-config
php artisan vendor:publish --tag=nova-ajax-select-lang
```

**[Full installation guide →](https://gabrielesbaiz.github.io/nova-ajax-select/#/install)**

## Usage

### A dependent field

`parent()` declares which attributes to watch. When one of them changes, Nova
re-resolves this field on the server and sends back a fresh option set.

```php
use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;

AjaxSelect::make('City', 'city_id')
    ->parent('province_id')
    ->options(fn ($context) => City::where('province_id', $context->parent())->pluck('name', 'id')),
```

That is the whole feature. There is no route to register, because the request
is Nova's own field-sync request — which means it is authorized by Nova, for
this resource, for this user, before your closure is ever called.

### Chains

Each level emits its own change, so chains of any depth work:

```php
Select::make('Country', 'country_id')->options($countries),

AjaxSelect::make('State', 'state_id')
    ->parent('country_id')
    ->optionsFromModel(State::class, query: fn ($q, $c) => $q->where('country_id', $c->parent())),

AjaxSelect::make('City', 'city_id')
    ->parent('state_id')
    ->optionsFromModel(City::class, query: fn ($q, $c) => $q->where('state_id', $c->parent())),
```

Change the country and the state clears; the state clearing is what clears the
city. You do not wire that up.

### Several parents

Options resolve once **every** declared parent has a value:

```php
AjaxSelect::make('Model', 'model_id')
    ->parent('brand_id', 'year')
    ->options(fn ($context) => CarModel::query()
        ->where('brand_id', $context->parent('brand_id'))
        ->where('year', $context->parent('year'))
        ->pluck('name', 'id')),
```

### Several children on one parent

Nothing special — point them at the same attribute:

```php
BelongsTo::make('Project'),

AjaxSelect::make('File', 'file_id')->parent('project')->optionsFromModel(File::class, ...),
AjaxSelect::make('Comment', 'comment_id')->parent('project')->optionsFromModel(Comment::class, ...),
```

### Inside actions

Action modals are ordinary dependent-field territory in Nova 5, so the field
works there unchanged:

```php
public function fields(NovaRequest $request): array
{
    return [
        Select::make('Province', 'province_id')->options($this->provinces()),

        AjaxSelect::make('City', 'city_id')
            ->parent('province_id')
            ->optionsFromModel(City::class, query: fn ($q, $c) => $q->where('province_id', $c->parent())),
    ];
}
```

Inside an action there is no model being edited; `$context->isAction()` is true
and `$context->model()` returns `null`.

### Composing with Nova's own features

Because `parent()` is built on `dependsOn()` rather than beside it, everything
else composes:

```php
AjaxSelect::make('City', 'city_id')
    ->parent('province_id')
    ->optionsFromModel(City::class, query: fn ($q, $c) => $q->where('province_id', $c->parent()))
    ->hide()
    ->dependsOn('has_address', function (AjaxSelect $field, NovaRequest $request, FormData $formData): void {
        if ($formData->boolean('has_address')) {
            $field->show()->rules('required');
        }
    })
    ->readonly(fn ($request) => ! $request->user()->isAdmin()),
```

Your `dependsOn()` callback runs **after** the options have been resolved, so it
can inspect or override them.

## The context

An options closure receives an `AjaxSelectContext` and the current
`NovaRequest`. Declare only the arguments you want — PHP tolerates the rest, so
`fn () =>`, `fn ($context) =>` and `fn ($context, $request) =>` are all valid.

```php
->options(function (AjaxSelectContext $context, NovaRequest $request) {
    // ...
})
```

| Member | Type | What it is |
|---|---|---|
| `parent()` | `mixed` | The first declared parent's value |
| `parent('province_id')` | `mixed` | A specific parent's value |
| `parents()` | `array` | All parent values, in declaration order |
| `parents('a', 'b')` | `array` | Those parents' values |
| `hasAllParents()` | `bool` | Are they all filled in? |
| `value` | `mixed` | The value currently selected in this field |
| `hasValue()` | `bool` | Is anything selected? |
| `search` | `?string` | The search term, when searching |
| `isSearching()` | `bool` | Is this a search request? |
| `limit` | `int` | How many options to return |
| `mode` | `string` | `create`, `update`, `attach`, `update-attached`, `action`, `index`, `detail` |
| `isAction()` | `bool` | Are we inside an action modal? |
| `isForm()` | `bool` | Is this a form request at all? |
| `model()` | `?Model` | The resource being edited — `null` on create and in actions |
| `user()` | `?Authenticatable` | The authenticated user |
| `request` | `NovaRequest` | The request itself |

Parent values always come from Nova's `FormData`, which is already restricted to
the attributes this field declared. A resolver never reads raw request input,
and cannot be steered by a key you did not ask for.

> [!NOTE]
> `model()` is a method, not a property, on purpose: resolving it costs a query
> that most closures never need. It is memoized, so asking twice is free.

## Option sources

```php
->options(['draft' => 'Draft', 'live' => 'Live'])
->options(fn ($context) => $geoService->citiesByProvince($context->parent()))
->optionsFromModel(City::class)
->optionsFromModel(City::class, label: 'name', value: 'code')
->optionsFromModel(City::class, query: fn ($q, $c) => $q->where('province_id', $c->parent()))
->optionsFromRelation('sellers')
->optionsFromEnum(Status::class)
```

Each source knows how to answer three different questions cheaply, which is why
validation and index rendering do not drag the whole option set into memory:

| Source | Resolve | Membership check | Single label |
|---|---|---|---|
| `optionsFromModel()` | one query, limited, search in SQL | `whereKey(...)->exists()` | `->value($label)` |
| `optionsFromRelation()` | one query on the relation | `whereKey(...)->exists()` | `->value($label)` |
| `optionsFromEnum()` | `cases()`, no I/O | `tryFrom()` | `tryFrom()` |
| `options([...])` | in memory | in memory | in memory |
| `options(fn () => ...)` | your closure, memoized | resolve, then scan | resolve, then scan |
| `get('/url')` (legacy) | the browser fetches | cannot know — skipped | cannot — field hides |

### Accepted payload shapes

Whatever your source returns is normalized to Nova's `{label, value}`:

```php
['it' => 'Italy', 'fr' => 'France']                      // value => label map
[['value' => 1, 'label' => 'Udine']]                     // Nova's own shape
[['value' => 1, 'display' => 'Udine']]                   // the 1.x shape
[['id' => 1, 'name' => 'Udine']]                         // id/name, e.g. an API payload
City::all()                                              // Eloquent models
Status::cases()                                          // backed enum instances
['options' => [...]] / ['data' => [...]]                 // common envelopes
```

`label` wins over `display` when both are present. Canonical numeric strings are
cast to integers exactly as Nova's own `Select` does, so `'12'` and `12` compare
equal against an integer foreign key. `group`, `subtitle` and `disabled` keys
are carried through.

## Searching

For sets too large to put in a form payload, let the server search:

```php
AjaxSelect::make('City', 'city_id')
    ->optionsFromModel(City::class)
    ->searchColumns('name', 'zip_code')
    ->asyncSearchable()
    ->minSearchLength(2)
    ->limit(25)
    ->debounce(400),
```

A model- or relation-backed field pushes the search into SQL across
`searchColumns()` (defaulting to the label column). Anything else filters in
PHP after resolving — or give it a dedicated callback, which keeps a model
source's cheap membership and label lookups intact:

```php
->searchUsing(fn (string $search, $context) => $geoService->searchCities($search))
```

Until the term is long enough the field resolves **nothing** — except the option
that is already selected, so an edit form still shows its label. A search that
happens not to include the stored value never silently clears it.

The search term travels inside Nova's own field-sync request, so searching
needs no route and no separate authorization path.

## Index and detail

The field renders a label instead of a raw foreign key. Pick the cheapest
strategy that fits:

```php
->labelFrom('city.name')                         // read it off the resource
->labelUsing(fn ($value, $resource) => ...)      // resolve it yourself
->resolveLabelFromOptions()                      // ask the option source
->displayUsing(fn ($value) => ...)               // Nova's own, always wins
```

`labelFrom()` is the one to reach for: it is a `data_get()` on the resource, so
with the relation in your resource's `$with` it costs **no extra query at all**.
Enum- and array-backed fields need no configuration.

`resolveLabelFromOptions()` is opt-in because it asks the source per value. It
is memoized per request, so fifty rows pointing at six cities cost six lookups,
not fifty.

If the field has no way to resolve a label — a plain endpoint-mode field, for
instance — it **hides itself** on index and detail rather than showing a bare
id. Upgrading cannot make an existing screen worse.

## Validation

Submitted values are checked against the resolved options automatically.

```php
AjaxSelect::make('City', 'city_id')
    ->parent('province_id')
    ->optionsFromModel(City::class, query: fn ($q, $c) => $q->where('province_id', $c->parent()))
    ->rules('required'),
```

A forged `city_id` is rejected even when the id exists, because the check runs
against **the submitted province**. The rule is lazy: it asks the source whether
it contains one value — a single `exists` query — rather than materializing
every option on every save.

```php
->withoutOptionValidation()                                  // opt out
->validateOptions(fn ($request) => $request->user()->isAdmin())
->validateOptionsUsing(fn ($value, $context) => ...)         // decide yourself
->optionValidationMessage('Pick a city in that province.')
```

It is skipped automatically in endpoint mode, where the option set genuinely
lives in your application's route and the field cannot know it.

> [!WARNING]
> This stops a value that does not belong to the submitted parent. It cannot
> stop a *consistent* pair the user was never meant to see — scope the options
> query for that, exactly as you would scope a Nova `BelongsTo` relatable query.

## Caching

```php
->cacheFor(3600)
->cacheFor(now()->addDay(), store: 'redis')
->cacheScope(fn ($context) => tenant()->getKey())
->cacheTags(['geo'])
->cacheSearchResults()
->withoutCache()
```

The key is derived from the option source, the field, the parent values, the
limit and the locale. Searches bypass the cache by default, because one entry
per keystroke is worse than an indexed `LIKE`; `cacheSearchResults()` opts in.

> [!CAUTION]
> **The cache key is not scoped to the authenticated user or tenant.** Scoping
> it that way by default would destroy the hit rate for the 95% of fields that
> do not need it. If a field's options depend on who is asking, you must set
> `cacheScope()` — otherwise one tenant can be served another's options.

## API reference

### Dependency

| Method | Description |
|---|---|
| `parent(Field\|string ...$attributes)` | Watch one or more parent attributes |
| `parents(array $attributes)` | The same, from an array |
| `clearWhenParentChanges(bool $clear = true)` | Clear a value that is no longer valid. Default `true` |
| `emptyWhenParentMissing(bool $empty = true)` | Resolve nothing while a parent is empty. Default `true` |
| `parentAttributes()` / `hasParents()` | Inspect the declared parents |

### Options

| Method | Description |
|---|---|
| `options(iterable\|callable\|string $options)` | A map, a closure or an enum class-string |
| `optionsFromModel(string $model, string $label = 'name', ?string $value = null, ?Closure $query = null)` | An Eloquent model |
| `optionsFromRelation(string $relation, string $label = 'name', ?Closure $query = null)` | A relation on the edited model |
| `optionsFromEnum(string $enum, ?Closure $label = null, ?Closure $filter = null)` | A backed enum |
| `searchColumns(string ...$columns)` | Columns a database source searches |
| `withOptionSource(OptionSource $source)` | Your own source implementation |

### Search

| Method | Description |
|---|---|
| `asyncSearchable(callable\|bool $searchable = true)` | Load options as the user types |
| `searchUsing(Closure $callback)` | `($search, $context)` — implies `asyncSearchable()` |
| `minSearchLength(int $length)` | Characters before the first request |
| `limit(int $limit)` | Maximum options returned |
| `debounce(int $milliseconds)` | Nova's own; default 500 |

### Display

| Method | Description |
|---|---|
| `labelFrom(string $path)` | `data_get()` path on the resource |
| `labelUsing(Closure $callback)` | `($value, $resource, $request)` |
| `resolveLabelFromOptions(bool $resolve = true)` | Ask the option source, memoized |
| `displayUsingLabels()` | Alias of `resolveLabelFromOptions()` |

### Validation

| Method | Description |
|---|---|
| `validateOptions(callable\|bool $validate = true)` | Turn the check on or off |
| `withoutOptionValidation()` | Turn it off |
| `validateOptionsUsing(Closure $callback)` | `($value, $context): bool` |
| `optionValidationMessage(string\|Closure $message)` | Custom failure message |

### Caching

| Method | Description |
|---|---|
| `cacheFor(DateTimeInterface\|DateInterval\|int $ttl, ?string $store = null)` | Cache resolved options |
| `cacheScope(Closure\|array $scope)` | Add tenant or user to the key |
| `cacheTags(array $tags)` | Tag the entries |
| `cacheSearchResults(bool $cache = true)` | Cache searches too. Default `false` |
| `withoutCache()` | Never cache this field |

### Legacy

| Method | Description |
|---|---|
| `get(string $endpoint)` | **Deprecated.** The 1.x URL mode |
| `endpoint(string $url, ?Closure $transform = null)` | The same, non-deprecated spelling |
| `usesEndpoint()` / `endpointUrl()` | Inspect it |

Everything inherited from Nova's `Select` — `nullable()`, `rules()`,
`readonly()`, `help()`, `dependsOn()`, `canSee()`, `onlyOnForms()`,
`placeholder()`, `displayUsing()` — works as usual.

## Configuration

`config/nova-ajax-select.php` holds the defaults for fields that do not set
their own.

```php
return [
    'cache' => [
        'enabled' => false,     // cache resolved options by default
        'store'   => null,      // null uses the default store
        'ttl'     => 300,
        'prefix'  => 'nova-ajax-select',
    ],

    'search' => [
        'min_length' => 0,      // characters before the first search request
        'limit'      => 50,     // caps every option set, not just searches
        'debounce'   => 500,
    ],

    'validation' => [
        'enabled' => true,      // the production escape hatch
    ],
];
```

## Translations

English and Italian ship with the package. Publish them to change the wording,
or to add a language:

```bash
php artisan vendor:publish --tag=nova-ajax-select-lang
```

| Key |
|---|
| `Could not load the available options.` |
| `Loading options...` |
| `Choose a :field first` |
| `Type at least :count characters to search` |

The field reuses Nova's own `Choose an option` and `No Results Found.` rather
than duplicating them.

## Upgrading from 1.x

**Nothing is required.** `get()` and `parent()` keep working, the
`NovaAjaxSelect` class and `NovaAjaxSelectServiceProvider` still resolve, and a
legacy endpoint may keep returning `{value, display}`. Existing call sites also
get debounced and cancellable requests, a loading state, error reporting, and —
because `parent()` now rides Nova's dependency machinery — working
`dependsOn()`, `hide()`, `show()` and action-modal support.

### 1. Remove `alexwenzel/ajax-select` if you have it

Both packages registered `Nova::script('ajax-select', ...)`. Nova resolves
scripts by name with `->first()`, so with both installed **one bundle was never
served** and its field rendered as an unknown component. 2.0 uses a namespaced
handle, but there is no reason to keep both.

### 2. Delete the routes

```php
// before — plus a route in every tenant's routes file
AjaxSelect::make('City', 'city_id')
    ->get('/api/classifications/territories/it/cities/province/{province_id}')
    ->parent('province_id')

// after
AjaxSelect::make('City', 'city_id')
    ->parent('province_id')
    ->optionsFromModel(City::class, query: fn ($q, $c) => $q->where('province_id', $c->parent()))
    ->cacheFor(3600)
```

If the options come from a service rather than a model, the closure form takes
the route body verbatim:

```php
->options(fn ($context) => Cache::rememberForever(
    City::remoteByProvinceCacheKey($context->parent()),
    fn () => GeoService::citiesByProvince($context->parent())
))
```

### 3. Delete the shadow detail fields

```php
// before
AjaxSelect::make('City', 'city_id')->get(...)->parent(...)->onlyOnForms(),
Text::make('City', 'city_id', fn () => Str::upper($city?->get('name')))->onlyOnDetail(),

// after
AjaxSelect::make('City', 'city_id')->parent(...)->optionsFromModel(...)->labelFrom('city.name'),
```

### 4. Drop the `canSee()` workarounds

Any branch that exists only because `dependsOn()` did not fire on this field —
typically `if ($field instanceof AjaxSelect) return $field->canSee(...)` — can
go back to being an ordinary `hide()->dependsOn(...)`.

### 5. Add `cacheScope()` if your options are tenant-scoped

Only if you also use `cacheFor()`. See [Caching](#caching).

### Breaking changes

- PHP 8.3+, Laravel 12 or 13, Nova 5. `laravel/nova` is now a real dependency
  rather than a dev one.
- The asset handle and the Vue component names are namespaced.
- `showOnIndex` / `showOnDetail` are no longer hardcoded `false`. The field
  still hides itself when it cannot resolve a label, so no existing screen
  changes.
- Option validation is on by default, except in endpoint mode.
- `spatie/laravel-package-tools` is no longer a dependency.

Deprecated and removed in 3.0: `get()` and endpoint mode, the `NovaAjaxSelect`
and `NovaAjaxSelectServiceProvider` class names, and the `{value, display}`
payload shape.

## How it works

Worth knowing if you are debugging, or deciding whether to trust it.

`parent()` does not install a watcher of its own. It registers a single Nova
`Dependent` on the field, which means Nova's existing field-sync endpoints —
`creation-fields`, `update-fields`, the two pivot variants, `/{resource}/action`
and the lens equivalents — do all the work:

1. The user changes the parent. Nova's `DependentFormField` mixin hears it on
   the field event bus, which is namespaced per form, so panels, tabs, peek
   modals and action modals all behave the same.
2. Nova PATCHes its own sync endpoint. The correct form request authorizes it
   for this resource and this user, rebuilds the resource, and finds this field
   by attribute and component key.
3. The field's resolver runs, builds a context from `FormData`, resolves the
   options and puts them in the serialized field.
4. If the selected value is no longer among them, it is cleared to `''` — not
   `null`, which Nova reads as *keep the previous value* — which is what
   cascades the change to the next field in the chain.

**This package registers no routes and no controllers.** A package-owned
endpoint would have to re-implement resource resolution and authorization by
hand, which is where field packages tend to grow vulnerabilities. Searching
rides the same request, with the term in the payload.

The trade-off, stated plainly: each change re-serializes this field and rebuilds
that resource's `fields()`. That is the same cost Nova already pays for every
dependent field, and `limit` keeps the payload small — but if you are resolving
something genuinely expensive on every keystroke, reach for `cacheFor()` or
`minSearchLength()`.

## Troubleshooting

| Symptom | Cause |
|---|---|
| Field renders as an unknown component | Another package claimed the asset handle. Remove `alexwenzel/ajax-select` |
| Options never load | A parent is empty. That is deliberate — see `emptyWhenParentMissing()` |
| Searchable field starts empty | Also deliberate. It waits for `minSearchLength()` characters |
| The field is missing from index or detail | It has no label strategy. Add `labelFrom()`, `labelUsing()` or `resolveLabelFromOptions()` |
| Valid value rejected on save | The options query does not include it for the *submitted* parent. Check the scope, or `withoutOptionValidation()` |
| Stale options after a deploy | A `cacheFor()` entry. `cacheTags()` makes it flushable |
| One tenant sees another's options | `cacheFor()` without `cacheScope()` |
| Selection clears when typing | Only when the server no longer offers the value; a search that merely excludes it does not clear it |

## Documentation

| | |
|---|---|
| [Documentation site](https://gabrielesbaiz.github.io/nova-ajax-select/) | Everything: install, configure, operate |
| [SECURITY.md](SECURITY.md) | The threat model, and what is deliberate |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Architecture, ground rules, how to build |
| [CHANGELOG.md](CHANGELOG.md) | What changed, and when |

## Testing

```bash
composer test        # Pest — 103 tests, including every example on this page
composer format      # Pint
npm run build        # compile dist/
npm test             # Vitest — the option normalizer and the fetch state machine
```

The PHP suite drives Nova's real sync endpoints rather than mocking them. It
includes a regression test for the asset-handle collision that made the 1.x
field unreachable, and it runs **every code sample in this README**, so the
documentation cannot drift from the package without a test going red.

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
