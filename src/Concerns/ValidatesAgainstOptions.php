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

/**
 * Refusing values that are not actually selectable.
 */
trait ValidatesAgainstOptions
{
    protected ?bool $validatesOptions = null;

    /** @var (Closure(mixed, AjaxSelectContext): bool)|null */
    protected ?Closure $optionValidator = null;

    protected string|Closure|null $optionValidationMessage = null;

    public function validateOptions(callable|bool $validate = true): static
    {
        $this->validatesOptions = is_bool($validate)
            ? $validate
            : (bool) $validate(app(NovaRequest::class));

        return $this;
    }

    public function withoutOptionValidation(): static
    {
        $this->validatesOptions = false;

        return $this;
    }

    /**
     * Decide membership yourself, as `($value, $context)`.
     */
    public function validateOptionsUsing(Closure $callback): static
    {
        $this->optionValidator = $callback;

        return $this;
    }

    public function optionValidationMessage(string|Closure $message): static
    {
        $this->optionValidationMessage = $message;

        return $this;
    }

    public function passesOptionValidation(mixed $value, AjaxSelectContext $context): bool
    {
        if ($this->optionValidator !== null) {
            return (bool) ($this->optionValidator)($value, $context);
        }

        return $this->optionSource()->contains($value, $context);
    }

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

        // In endpoint mode the option set lives in the application's own route,
        // so there is genuinely nothing to check against.
        return ! $this->optionSource() instanceof EndpointSource;
    }

    /**
     * Turn whatever Nova handed back into a flat rule array.
     *
     * Laravel only pipe-explodes a rule *value*, never the members of a rule
     * array - it throws a BadMethodCallException on 'required|max:5' nested in
     * one. Nova stores rules('required|max:5') as exactly that nested string,
     * so appending our rule object would turn a working rule set into a fatal.
     * Exploding here keeps both halves intact.
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
