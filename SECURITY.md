# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 2.x | Yes |
| 1.x | No — upgrade, see [Upgrading from 1.x](README.md#upgrading-from-1x) |

## Reporting a vulnerability

Email **gabriele.sbaiz@noviasnet.it** with a description, the package version,
and a reproduction if you have one. Please do not open a public issue.

You will get an acknowledgement within 5 working days and an assessment within
15. If the report is valid you will be credited in the release notes unless you
ask not to be.

## Scope

**In scope:** anything that lets a request reach an options resolver it is not
authorized for, read options scoped to another user or tenant, bypass the
option validation rule with a value the field never offered, or reach the
database through a value this package interpolates rather than binds.

**Out of scope:** what an application's own options closure returns, and to
whom. The closure is application code running under application authorization —
if it queries without a tenant scope, this package will faithfully serve the
result. The same applies to routes used in legacy endpoint mode: they are the
application's routes, with the application's middleware.

## What the package defends against

**Unauthorized option resolution.** This package registers **no routes and no
controllers** — grep `src/` for `Route::` and you will find nothing. Every
resolution happens inside Nova's own field-sync endpoints (`creation-fields`,
`update-fields`, the two pivot variants, `/{resource}/action` and the lens
equivalents). Nova's form request authorizes the resource and the mode, rebuilds
the resource, and locates the field by attribute and component key *before* an
application closure is invoked. A package-owned endpoint would have to
re-implement resource resolution and authorization by hand, which is where field
packages tend to grow vulnerabilities; this one declines to have the problem.

**Resolver input cannot be steered by the request.** Parent values are read from
Nova's `FormData`, which is already restricted to the attributes the field
declared through `parent()` and `dependsOn()`. A closure calling
`$context->parent()` cannot be handed a key the field never asked for, and
`AjaxSelectContext` exposes no bulk accessor over raw input.

**Forged selections.** A submitted value is checked against the options the
field would actually offer *for the parent values submitted alongside it*, so
changing `city_id` to a city in another province is rejected even though the id
exists. The check is a single membership query — `whereKey(...)->exists()` for a
model source — rather than a materialized `Rule::in`, so it cannot be turned
into a memory-exhaustion vector by pointing a field at a large table.

**Search terms are bound, and wildcards are neutralized.** A search reaches the
database as a bound parameter, never as concatenated SQL, and `%` and `_` are
escaped before the term is wrapped so a user cannot widen their own query to the
whole table.

**Unbounded result sets.** Every option set is capped by `limit()`, which
defaults to `config('nova-ajax-select.search.limit')`. A field cannot be coaxed
into serializing an entire table into a form payload.

**The browser is not trusted to filter.** Options are resolved, searched and
limited on the server. In 1.x the browser fetched a list and filtered it; the
full list was in the payload whether the user was entitled to all of it or not.

## Design notes for reviewers

These are deliberate. Please do not report them as vulnerabilities.

**The options cache is not scoped to the user or tenant by default.** The key is
derived from the source, the field, the parent values, the limit and the locale
— not from who is asking. Scoping every entry by user would destroy the hit rate
for the large majority of fields whose options are global (provinces, statuses,
countries). **If a field's options depend on the tenant or the viewer, you must
set `cacheScope()`**, and caching is off unless you turn it on. This is the one
foot-gun in the package, it is documented in the README under a caution
callout, and it is a deliberate trade rather than an oversight.

**Column names are code, not request data.** `searchColumns()`, the `label` and
`value` arguments of `optionsFromModel()`, the relation name and the
`labelFrom()` path are all interpolated into a query or a `data_get()`. They come
from your resource definition. If an application derives one of them from a
request, that is an injection this package does not attempt to defend against —
the same way a `where()` column in your own code is your responsibility.

**Legacy endpoint mode validates nothing, on purpose.** With `->get('/url')` the
option set lives in your route, so the field genuinely cannot know it. Option
validation is skipped rather than guessed, and the field hides itself on index
and detail rather than rendering a raw foreign key. Endpoint mode is deprecated;
`options()` and `optionsFromModel()` are validated.

**The search term rides Nova's sync payload under `__ajaxSelectSearch`.** It is
read only through `AjaxSelectContext`, it is never filled into the model — the
field fills only its own attribute — and Laravel's validator ignores keys with
no rule.

**A cleared value is set to `''`, not `null`.** Nova's `DependentFormField`
treats a null synced value as "keep the previous one" and reverts, so clearing
with null would leave a now-invalid selection in place on the client. The empty
string is what actually clears the input and cascades to child fields.

**The package makes no outbound requests and writes nothing to disk.** No HTTP
client, no `curl`, no `file_get_contents`, no logging of resolved options. What
an application's own closure does is its own business.

**`dist/js/field.js` is committed.** A Nova field is unusable without its
compiled bundle and there is no CI to build one on release, so the repository
carries it. It is built from `resources/js/` with `npm run build` and contains
no vendored dependencies — Vue and Nova's runtime are externals resolved from
globals at load time.
