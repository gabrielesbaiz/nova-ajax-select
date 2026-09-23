# Contributing

## Getting set up

Nova is a paid, private Composer package, so you will need a licence.

```bash
composer config --global http-basic.nova.laravel.com "you@example.com" "your-licence-key"
composer install
npm ci && npm run build
```

Then:

```bash
composer test      # Pest
composer format    # Pint
npm test           # Vitest
npm run build      # compile dist/
```

There is no CI. Those four commands are the whole gate, so please run all of
them — nothing downstream will catch it for you.

## Ground rules

**Every README example is a test.** `tests/ReadmeExamplesTest.php` executes the
code samples on the documentation page. If you change an API the README shows,
that file goes red before anything else does, and updating it is part of the
change rather than a follow-up.

**Do not add a route.** This package deliberately owns no HTTP endpoint: every
option resolution rides Nova's own field-sync request, which means Nova has
already authorized the resource, the mode and the user before an application
closure runs. Adding a package-owned endpoint would mean re-implementing
resource resolution and authorization by hand. If you think you need one, the
answer is almost certainly a new `OptionSource` instead.

**Do not walk the Vue component tree.** 1.x found its parent field with
`walk(this.$parent.$.subTree)`, reading `vnode.shapeFlag & 16`. That is why
`dependsOn()`, `hide()`, action modals and pivot forms did not work on the field,
and why consumers had to fall back to `canSee()`. Parent changes arrive on Nova's
field event bus through `getFieldAttributeChangeEventName()`, which is namespaced
per form. Build the event name with that helper; never by hand.

**Every Nova import goes through `resources/js/nova.js`.** It is the single seam
over Nova's internals, so a rename in a future Nova release is a one-file change.
A bare `import { ... } from 'laravel-nova'` anywhere else is a review comment.

**A new option source implements `OptionSource` in full.** All five methods
matter, and three of them exist precisely so the expensive path is avoidable:
`resolve()` for the dropdown, `contains()` for validation (one membership query,
not a materialized list), `label()` for index and detail (one value, not the
whole set), `signature()` for cache keys, and `isCheapToLabel()` to decide
whether the field may appear on an index at all. A source that answers
`contains()` by resolving everything is a source that will fall over on a large
table.

**Option payload shapes are append-only.** `OptionCollection::make()` accepts
value-label maps, Nova's `{value, label}`, the legacy `{value, display}`,
`{id, name}`, models, enums, `Arrayable` and common envelopes. Applications have
these shapes in production. Add a shape if you need one; do not remove one.

## Architecture in one paragraph

`AjaxSelect` extends Nova's `Select` and composes seven traits from
`src/Concerns/`. `parent()` registers a single `Dependent` whose resolver is
`Support\ResolveAjaxSelectOptions`; Nova invokes it on every field sync. The
resolver builds an `AjaxSelectContext` from `FormData`, hands it to an
`OptionSource` from `src/Sources/`, and normalizes whatever comes back through
`Support\OptionCollection` into Nova's `{label, value}`. Validation, caching and
display resolution all go through the same source, which is why adding one is
enough to teach the field a new way to find options.

## Testing

The PHP suite drives Nova's real controllers rather than mocking them —
`tests/CreationSyncTest.php` PATCHes the actual sync endpoints, which is the only
way to catch the class of bug that made 1.x unusable. Fixtures live in
`tests/Fixtures`, and `tests/TestCase.php` flushes Nova's static state between
tests, because Nova keeps registered scripts, styles and translations in static
properties and they otherwise leak across the suite.

Run it more than once before submitting. Pest randomizes order, and an
order-dependent failure will only appear on some seeds:

```bash
vendor/bin/pest && vendor/bin/pest && vendor/bin/pest
```

The frontend suite covers what the build cannot: the payload normalizer and the
fetch state machine — abort semantics, the selected option surviving a filtered
search, cascade clearing. It stubs `laravel-nova`, which only exists inside a
running Nova app.

## Front end and `dist/`

The bundle is built with Vite, not Mix. `vue`, `laravel-nova`, `laravel-nova-ui`
and `laravel-nova-util` are **externals** mapped to the globals Nova assigns —
exactly the set `laravel/nova-devtool`'s Mix extension declares. Bundling any of
them would ship a second Vue runtime into a page that already has one.

`dist/` **is committed**: a Nova field is unusable without its compiled bundle,
and with no CI to build one on release the repository has to carry it. The build
is deterministic, so rebuild and check for drift before tagging:

```bash
npm ci && npm run build && git diff --exit-code -- dist
```

This is the thing to get wrong. 1.x shipped a `FormField.vue` whose template had
been replaced but whose script had not, so its searchable branch referenced
methods that did not exist and was dead code in every release that carried it.

Components are registered **PascalCase** (`FormGabrielesbaizAjaxSelect`).
`Nova.hasComponent()` capitalizes and camelizes a name before looking it up, so
a kebab-case registration is invisible to it, while Vue resolves
`<component is="form-gabrielesbaiz-ajax-select">` either way.

## Naming and collisions

The asset handle is `gabrielesbaiz-nova-ajax-select` and the field component is
`gabrielesbaiz-ajax-select`. Both are namespaced because 1.x shared the bare
`ajax-select` handle with `alexwenzel/ajax-select`, and Nova resolves scripts by
name with `->first()` — so with both packages installed, one bundle was simply
never served and its field rendered as an unknown component. There is a
regression test for this in `tests/ServiceProviderTest.php`. Please do not
shorten either name.

## Pull requests

1. Branch from `main`.
2. Keep the gates green: Pest, Pint, Vitest, and a clean `dist/` diff.
3. Describe the behaviour change, not just the diff.
4. Update `CHANGELOG.md`.
5. If you changed a documented API, update `README.md` — and its test.

Security issues go to the address in [SECURITY.md](SECURITY.md), not to a public
pull request.
