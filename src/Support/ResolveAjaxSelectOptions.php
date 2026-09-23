<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Support;

use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * The resolver behind the field's own Dependent.
 *
 * Nova calls this on every field sync - creation, update, pivot and action
 * alike - which is why this package needs no HTTP route of its own.
 */
final class ResolveAjaxSelectOptions
{
    public function __invoke(AjaxSelect $field, NovaRequest $request, FormData $formData): void
    {
        $context = AjaxSelectContext::forSync($field, $request, $formData);

        $field->withResolvedContext($context);

        // Nova blanks the value before calling us, so anything still valid has
        // to be put back explicitly or every sync would silently clear the field.
        if (! $context->hasValue()) {
            return;
        }

        if (! $field->clearsWhenParentChanges() || $field->optionSource()->contains($context->value, $context)) {
            $field->value = $context->value;

            return;
        }

        // Empty string, not null: DependentFormField.js treats a null synced
        // value as "keep the previous one" and reverts, while '' clears the
        // input and flips dependentShouldEmitChangesEvent - which is what makes
        // a three-level chain cascade down to its grandchildren.
        $field->value = '';
    }
}
