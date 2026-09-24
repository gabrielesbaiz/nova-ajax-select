<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Support;

use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Http\Requests\NovaRequest;

final class ResolveAjaxSelectOptions
{
    /**
     * Resolve the field's options for the given field sync.
     */
    public function __invoke(AjaxSelect $field, NovaRequest $request, FormData $formData): void
    {
        $context = AjaxSelectContext::forSync($field, $request, $formData);

        $field->withResolvedContext($context);

        // Nova blanks the value before this runs, so a still valid value
        // has to be restored explicitly.
        if (! $context->hasValue()) {
            return;
        }

        if (! $field->clearsWhenParentChanges() || $field->optionSource()->contains($context->value, $context)) {
            $field->value = $context->value;

            return;
        }

        // Empty string, not null: Nova keeps the previous value on a null
        // sync, while an empty string clears the input and cascades the change.
        $field->value = '';
    }
}
