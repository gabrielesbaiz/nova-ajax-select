/**
 * Stand-in for the `laravel-nova` runtime global, which only exists inside a
 * running Nova app. The mixins are irrelevant to the logic under test; the
 * build treats this module as an external either way.
 */
export const DependentFormField = {};
export const FieldValue = {};
export const HandlesValidationErrors = {};
