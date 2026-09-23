<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Rules;

use Closure;
use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Assert that the submitted value really is one of the field's options.
 *
 * Deliberately lazy: asking the source whether it contains one value is a
 * single indexed query, while Rule::in() would force every option to be
 * materialized on every save.
 */
final class ValueIsAnOption implements ValidationRule
{
    public function __construct(
        private readonly AjaxSelect $field,
        private readonly string|Closure|null $message = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Presence is required/nullable's job, not ours.
        if ($value === null || $value === '' || $this->field->isValidNullValue($value)) {
            return;
        }

        $context = AjaxSelectContext::forValidation($this->field, app(NovaRequest::class));

        if ($this->field->passesOptionValidation($value, $context)) {
            return;
        }

        $message = $this->message instanceof Closure ? ($this->message)($value, $context) : $this->message;

        $fail($message ?? __('The selected :attribute is invalid.', ['attribute' => $this->field->name]));
    }
}
