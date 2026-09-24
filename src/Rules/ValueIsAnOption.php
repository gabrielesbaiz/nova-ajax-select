<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Rules;

use Closure;
use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Laravel\Nova\Http\Requests\NovaRequest;

final class ValueIsAnOption implements ValidationRule
{
    /**
     * Create a new rule instance.
     */
    public function __construct(
        private readonly AjaxSelect $field,
        private readonly string|Closure|null $message = null,
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Presence is the job of the required and nullable rules.
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
