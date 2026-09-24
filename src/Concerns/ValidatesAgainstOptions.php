<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Concerns;

use Closure;
use Gabrielesbaiz\NovaAjaxSelect\Rules\ValueIsAnOption;
use Gabrielesbaiz\NovaAjaxSelect\Sources\EndpointSource;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Nova\Http\Requests\NovaRequest;

trait ValidatesAgainstOptions
{
    /**
     * Indicates if submitted values are checked against the option set.
     */
    protected ?bool $validatesOptions = null;

    /**
     * The callback used to determine option membership.
     *
     * @var (Closure(mixed, AjaxSelectContext): bool)|null
     */
    protected ?Closure $optionValidator = null;

    /**
     * The message shown when the submitted value is not an option.
     */
    protected string|Closure|null $optionValidationMessage = null;

    /**
     * Check submitted values against the option set.
     */
    public function validateOptions(callable|bool $validate = true): static
    {
        $this->validatesOptions = is_bool($validate)
            ? $validate
            : (bool) $validate(app(NovaRequest::class));

        return $this;
    }

    /**
     * Disable checking submitted values against the option set.
     */
    public function withoutOptionValidation(): static
    {
        $this->validatesOptions = false;

        return $this;
    }

    /**
     * Determine option membership using the given callback.
     *
     * The callback is invoked as ($value, $context).
     */
    public function validateOptionsUsing(Closure $callback): static
    {
        $this->optionValidator = $callback;

        return $this;
    }

    /**
     * Set the message shown when the submitted value is not an option.
     */
    public function optionValidationMessage(string|Closure $message): static
    {
        $this->optionValidationMessage = $message;

        return $this;
    }

    /**
     * Determine if the given value is one of the field's options.
     */
    public function passesOptionValidation(mixed $value, AjaxSelectContext $context): bool
    {
        if ($this->optionValidator !== null) {
            return (bool) ($this->optionValidator)($value, $context);
        }

        return $this->optionSource()->contains($value, $context);
    }

    /**
     * Get the validation rules for the field.
     *
     * @return array<string, array<int, mixed>>
     */
    #[\Override]
    public function getRules(NovaRequest $request): array
    {
        $rules = parent::getRules($request);

        if (! $this->shouldValidateOptions($request)) {
            return $rules;
        }

        return [$this->attribute => [
            ...$this->normalizeRules($rules[$this->attribute] ?? []),
            new ValueIsAnOption($this, $this->optionValidationMessage),
        ]];
    }

    /**
     * Determine if submitted values should be checked against the option set.
     */
    protected function shouldValidateOptions(NovaRequest $request): bool
    {
        if ($this->validatesOptions === false) {
            return false;
        }

        if ($this->optionValidator !== null) {
            return true;
        }

        if ($this->validatesOptions !== true && ! config('nova-ajax-select.validation.enabled', true)) {
            return false;
        }

        // In endpoint mode the option set lives in the application's own route.
        return ! $this->optionSource() instanceof EndpointSource;
    }

    /**
     * Flatten the given rules into an array of individual rules.
     *
     * Laravel pipe-explodes a rule value but not the members of a rule array,
     * so a nested "required|max:5" string has to be split here.
     *
     * @return array<int, mixed>
     */
    protected function normalizeRules(mixed $rules): array
    {
        $normalized = [];

        foreach (Arr::wrap($rules) as $rule) {
            if (is_string($rule) && ! Str::startsWith($rule, ['regex:', 'not_regex:'])) {
                array_push($normalized, ...explode('|', $rule));

                continue;
            }

            $normalized[] = $rule;
        }

        return $normalized;
    }
}
