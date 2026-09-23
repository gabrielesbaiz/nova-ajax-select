# Changelog

All notable changes to `nova-ajax-select` will be documented in this file.

## 2.0.0

### Fixed

- **The script handle no longer collides with `alexwenzel/ajax-select`.** Both
  packages registered `Nova::script('ajax-select', ...)`; Nova resolves scripts
  by name with `->first()`, so with both installed one bundle was never served
  and its field rendered as an unknown component.
- The searchable branch of the form field was dead code: the template had been
  copied from Nova's `SelectField.vue` without the script that backs it.

### Added

- Server-side option resolution: `options()`, `optionsFromModel()`,
  `optionsFromRelation()`, `optionsFromEnum()` and `searchUsing()`.
- Server-side search: `asyncSearchable()`, `minSearchLength()`, `limit()`.
- Index and detail label rendering: `labelFrom()`, `labelUsing()`,
  `resolveLabelFromOptions()`.
- Automatic validation of submitted values, with `withoutOptionValidation()`,
  `validateOptionsUsing()` and `optionValidationMessage()`.
- Caching: `cacheFor()`, `cacheScope()`, `cacheTags()`, `cacheSearchResults()`,
  `withoutCache()`.
- Multiple parents, `clearWhenParentChanges()` and `emptyWhenParentMissing()`.
- A configuration file and English and Italian translations.
- A real test suite: Pest for PHP, Vitest for the frontend.

### Changed

- `parent()` is now implemented on top of Nova's dependent-field machinery
  instead of walking the Vue component tree, so the field composes with
  `dependsOn()`, `hide()`, `show()` and `readonly()` and works inside panels,
  pivot forms and action modals.
- Requests are debounced and cancellable, with a loading state and error
  reporting.
- `showOnIndex` / `showOnDetail` are no longer hardcoded `false`; the field hides
  itself only when it cannot resolve a label.
- The build moved from Laravel Mix to Vite.
- Requires PHP 8.3+, Laravel 12 or 13 and Nova 5; `laravel/nova` moved back into
  `require`.
- Dropped the unused `spatie/laravel-package-tools` dependency.

### Deprecated

- `get()` and endpoint mode, the `NovaAjaxSelect` class name, the
  `NovaAjaxSelectServiceProvider` name and the `{value, display}` payload shape.
  All still work; all are removed in 3.0.

## 1.0.0

- Initial release, based on `alexwenzel/nova-ajax-select`.
