<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Concerns;

use Closure;
use Laravel\Nova\Http\Requests\NovaRequest;

trait ResolvesDisplayValues
{
    /**
     * The path the display label should be read from on the resource.
     */
    protected ?string $labelPath = null;

    /**
     * The callback used to resolve the display label.
     *
     * @var (Closure(mixed, mixed, NovaRequest): (string|null))|null
     */
    protected ?Closure $labelCallback = null;

    /**
     * Indicates if display labels should be resolved through the option source.
     */
    protected bool $resolveLabelFromOptions = false;

    /**
     * Read the display label off the resource, e.g. "city.name".
     */
    public function labelFrom(string $path): static
    {
        $this->labelPath = $path;

        return $this;
    }

    /**
     * Resolve the display label using the given callback.
     *
     * The callback is invoked as ($value, $resource, $request).
     */
    public function labelUsing(Closure $callback): static
    {
        $this->labelCallback = $callback;

        return $this;
    }

    /**
     * Resolve display labels through the option source.
     *
     * Costs one lookup per distinct value, memoized for the request.
     */
    public function resolveLabelFromOptions(bool $resolve = true): static
    {
        $this->resolveLabelFromOptions = $resolve;

        return $this;
    }

    /**
     * Determine if the field is shown on the index view.
     */
    #[\Override]
    public function isShownOnIndex(NovaRequest $request, $resource): bool
    {
        return parent::isShownOnIndex($request, $resource) && $this->canResolveDisplayValue();
    }

    /**
     * Determine if the field is shown on the detail view.
     */
    #[\Override]
    public function isShownOnDetail(NovaRequest $request, $resource): bool
    {
        return parent::isShownOnDetail($request, $resource) && $this->canResolveDisplayValue();
    }

    /**
     * Determine if the field is able to resolve a display label.
     */
    public function canResolveDisplayValue(): bool
    {
        return $this->labelPath !== null
            || $this->labelCallback !== null
            || $this->displayCallback !== null
            || $this->resolveLabelFromOptions
            || $this->optionSource()->isCheapToLabel();
    }

    /**
     * Resolve the field's value for display.
     */
    #[\Override]
    public function resolveForDisplay($resource, ?string $attribute = null): void
    {
        parent::resolveForDisplay($resource, $attribute);

        if ($this->displayCallback !== null || $this->usesCustomizedDisplay) {
            return;
        }

        $label = $this->resolveDisplayLabel($this->value, $resource);

        if ($label !== null) {
            $this->usesCustomizedDisplay = true;
            $this->displayedAs = $label;
        }
    }

    /**
     * Resolve the display label for the given value.
     */
    protected function resolveDisplayLabel(mixed $value, mixed $resource): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($this->labelCallback !== null) {
            return ($this->labelCallback)($value, $resource, app(NovaRequest::class));
        }

        if ($this->labelPath !== null) {
            $label = data_get($resource, $this->labelPath);

            return $label === null ? null : (string) $label;
        }

        $source = $this->optionSource();

        if (! $this->resolveLabelFromOptions && ! $source->isCheapToLabel()) {
            return null;
        }

        $signature = $source->signature();
        $cacheKey = (string) $value;

        if (! isset(static::$labelMemo[$signature][$cacheKey])) {
            static::$labelMemo[$signature][$cacheKey] = $source->label($value, $this->currentContext());
        }

        return static::$labelMemo[$signature][$cacheKey];
    }

    /**
     * The resolved labels, memoized for the request across every index row.
     *
     * @var array<string, array<string, string|null>>
     */
    protected static array $labelMemo = [];

    /**
     * Flush the memoized display labels.
     */
    public static function flushLabelMemo(): void
    {
        static::$labelMemo = [];
    }
}
