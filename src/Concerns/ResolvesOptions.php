<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Concerns;

use BackedEnum;
use Closure;
use Gabrielesbaiz\NovaAjaxSelect\Contracts\OptionSource;
use Gabrielesbaiz\NovaAjaxSelect\Sources\ArraySource;
use Gabrielesbaiz\NovaAjaxSelect\Sources\CallbackSource;
use Gabrielesbaiz\NovaAjaxSelect\Sources\EnumSource;
use Gabrielesbaiz\NovaAjaxSelect\Sources\ModelSource;
use Gabrielesbaiz\NovaAjaxSelect\Sources\RelationSource;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Support\Option;
use Gabrielesbaiz\NovaAjaxSelect\Support\OptionCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Declaring and resolving the option set.
 */
trait ResolvesOptions
{
    protected ?OptionSource $optionSource = null;

    protected ?AjaxSelectContext $resolvedContext = null;

    /** @var array<string, OptionCollection> */
    protected array $resolvedOptions = [];

    /** @var array<int, string> */
    protected array $searchColumns = [];

    /**
     * Set the options for the field.
     *
     * A callable receives `(AjaxSelectContext $context, NovaRequest $request)`.
     * PHP tolerates extra arguments, so `fn () =>`, `fn ($context) =>` and
     * `fn ($context, $request) =>` are all valid.
     *
     * @param  iterable<array-key, mixed>|callable|class-string<BackedEnum>  $options
     */
    public function options(iterable|callable|string $options): static
    {
        if (is_string($options) && enum_exists($options)) {
            return $this->optionsFromEnum($options);
        }

        return $this->withOptionSource(
            is_callable($options) ? new CallbackSource($options) : new ArraySource($options)
        );
    }

    /**
     * Resolve options from an Eloquent model.
     *
     * @param  class-string<Model>  $model
     * @param  (Closure(Builder, AjaxSelectContext): mixed)|null  $query
     */
    public function optionsFromModel(
        string $model,
        string $label = 'name',
        ?string $value = null,
        ?Closure $query = null,
    ): static {
        return $this->withOptionSource(new ModelSource($model, $label, $value, $query, $this->searchColumns));
    }

    /**
     * Resolve options from a relation on the resource model being edited.
     *
     * @param  (Closure(Builder, AjaxSelectContext): mixed)|null  $query
     */
    public function optionsFromRelation(string $relation, string $label = 'name', ?Closure $query = null): static
    {
        return $this->withOptionSource(new RelationSource($relation, $label, $query, $this->searchColumns));
    }

    /**
     * Resolve options from a backed enum.
     *
     * @param  class-string<BackedEnum>  $enum
     * @param  (Closure(BackedEnum): string)|null  $label
     * @param  (Closure(BackedEnum): bool)|null  $filter
     */
    public function optionsFromEnum(string $enum, ?Closure $label = null, ?Closure $filter = null): static
    {
        return $this->withOptionSource(new EnumSource($enum, $label, $filter));
    }

    /**
     * Columns a database-backed source should search. Defaults to the label column.
     */
    public function searchColumns(string ...$columns): static
    {
        $this->searchColumns = array_values($columns);

        if ($this->optionSource !== null && method_exists($this->optionSource, 'withSearchColumns')) {
            $this->optionSource->withSearchColumns($this->searchColumns);
        }

        return $this;
    }

    public function withOptionSource(OptionSource $source): static
    {
        $this->optionSource = $source;
        $this->resolvedOptions = [];

        return $this;
    }

    public function optionSource(): OptionSource
    {
        return $this->optionSource ??= new ArraySource([]);
    }

    /**
     * Remember the context built during a dependent-field sync so serialization,
     * validation and display all reuse the same parent values.
     */
    public function withResolvedContext(AjaxSelectContext $context): static
    {
        $this->resolvedContext = $context;

        return $this;
    }

    public function currentContext(?NovaRequest $request = null): AjaxSelectContext
    {
        return $this->resolvedContext ??= AjaxSelectContext::forRequest(
            $this,
            $request ?? app(NovaRequest::class)
        );
    }

    /**
     * Resolve the option set, honouring the cache when one is configured.
     */
    public function resolveOptions(AjaxSelectContext $context): OptionCollection
    {
        $key = $this->optionsCacheKey($context);

        if (isset($this->resolvedOptions[$key])) {
            return $this->resolvedOptions[$key];
        }

        return $this->resolvedOptions[$key] = $this->rememberOptions(
            $context,
            $key,
            fn (): OptionCollection => $this->searchResolver !== null && $context->isSearching()
                ? OptionCollection::make(($this->searchResolver)($context->search, $context))->take($context->limit ?: null)
                : $this->optionSource()->resolve($context)
        );
    }

    /**
     * Decide whether resolving the full option set is worth it for this request.
     */
    protected function shouldResolveOptions(AjaxSelectContext $context): bool
    {
        if (! $context->isForm()) {
            return false;
        }

        if ($this->emptyWhenParentMissing && ! $context->hasAllParents()) {
            return false;
        }

        // An async-searchable field must not dump its whole table into the
        // form payload; it waits for a search term.
        if ($this->isAsyncSearchable() && ! $this->searchIsLongEnough($context)) {
            return false;
        }

        return true;
    }

    /**
     * The one option we can still show without resolving anything: the stored value.
     */
    protected function selectedOptionOnly(AjaxSelectContext $context): OptionCollection
    {
        return OptionCollection::make([])->prepend($this->selectedOption($context));
    }

    protected function selectedOption(AjaxSelectContext $context): ?Option
    {
        $value = $context->hasValue() ? $context->value : $this->value;

        if ($value === null || $value === '') {
            return null;
        }

        $label = $this->optionSource()->label($value, $context);

        return $label === null ? null : new Option(Option::castValue($value), $label);
    }

    /**
     * Serialize options for the field. Overrides Nova's Select.
     *
     * @return array<int, array<string, mixed>>
     */
    #[\Override]
    protected function serializeOptions(bool $searchable): array
    {
        $context = $this->currentContext();

        if (! $this->shouldResolveOptions($context)) {
            return $this->selectedOptionOnly($context)->serialize();
        }

        return $this->resolveOptions($context)
            ->prepend($this->selectedOption($context))
            ->serialize();
    }
}
