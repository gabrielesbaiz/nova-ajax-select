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

trait ResolvesOptions
{
    /**
     * The source the field's options are resolved from.
     */
    protected ?OptionSource $optionSource = null;

    /**
     * The context resolved for the current request.
     */
    protected ?AjaxSelectContext $resolvedContext = null;

    /**
     * The resolved option sets, keyed by cache key.
     *
     * @var array<string, OptionCollection>
     */
    protected array $resolvedOptions = [];

    /**
     * The columns a database-backed source should search.
     *
     * @var array<int, string>
     */
    protected array $searchColumns = [];

    /**
     * Set the options for the field.
     *
     * A callable is invoked as ($context, $request).
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
     * Set the columns a database-backed source should search.
     *
     * Defaults to the label column.
     */
    public function searchColumns(string ...$columns): static
    {
        $this->searchColumns = array_values($columns);

        if ($this->optionSource !== null && method_exists($this->optionSource, 'withSearchColumns')) {
            $this->optionSource->withSearchColumns($this->searchColumns);
        }

        return $this;
    }

    /**
     * Set the source the field's options are resolved from.
     */
    public function withOptionSource(OptionSource $source): static
    {
        $this->optionSource = $source;
        $this->resolvedOptions = [];

        return $this;
    }

    /**
     * Get the source the field's options are resolved from.
     */
    public function optionSource(): OptionSource
    {
        return $this->optionSource ??= new ArraySource([]);
    }

    /**
     * Set the context that serialization, validation and display should reuse.
     */
    public function withResolvedContext(AjaxSelectContext $context): static
    {
        $this->resolvedContext = $context;

        return $this;
    }

    /**
     * Get the context for the current request.
     */
    public function currentContext(?NovaRequest $request = null): AjaxSelectContext
    {
        return $this->resolvedContext ??= AjaxSelectContext::forRequest(
            $this,
            $request ?? app(NovaRequest::class)
        );
    }

    /**
     * Resolve the option set for the given context.
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
     * Determine if the full option set should be resolved for the given context.
     */
    protected function shouldResolveOptions(AjaxSelectContext $context): bool
    {
        if (! $context->isForm()) {
            return false;
        }

        if ($this->emptyWhenParentMissing && ! $context->hasAllParents()) {
            return false;
        }

        // An async-searchable field waits for a search term rather than
        // serializing its whole table into the form payload.
        if ($this->isAsyncSearchable() && ! $this->searchIsLongEnough($context)) {
            return false;
        }

        return true;
    }

    /**
     * Get the stored value as the only option, without resolving the source.
     */
    protected function selectedOptionOnly(AjaxSelectContext $context): OptionCollection
    {
        return OptionCollection::make([])->prepend($this->selectedOption($context));
    }

    /**
     * Get the option matching the currently stored value.
     */
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
     * Serialize options for the field.
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
