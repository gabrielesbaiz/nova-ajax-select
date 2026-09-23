<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Concerns;

use Closure;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Rendering a label instead of a raw foreign key on index and detail.
 *
 * The field only appears there when it can actually resolve a label, so an
 * existing endpoint-mode call site never starts showing bare ids.
 */
trait ResolvesDisplayValues
{
    protected ?string $labelPath = null;

    /** @var (Closure(mixed, mixed, NovaRequest): (string|null))|null */
    protected ?Closure $labelCallback = null;

    protected bool $resolveLabelFromOptions = false;

    /**
     * Read the label off the resource, e.g. `labelFrom('city.name')`.
     *
     * The cheapest option: no extra query at all when the relation is eager
     * loaded through the resource's `$with`.
     */
    public function labelFrom(string $path): static
    {
        $this->labelPath = $path;

        return $this;
    }

    /**
     * Resolve the label yourself, as `($value, $resource, $request)`.
     */
    public function labelUsing(Closure $callback): static
    {
        $this->labelCallback = $callback;

        return $this;
    }

    /**
     * Resolve labels through the option source.
     *
     * Opt-in because it costs a lookup per distinct value; results are
     * memoized per request, so repeated values on an index page are free.
     */
    public function resolveLabelFromOptions(bool $resolve = true): static
    {
        $this->resolveLabelFromOptions = $resolve;

        return $this;
    }

    #[\Override]
    public function isShownOnIndex(NovaRequest $request, $resource): bool
    {
        return parent::isShownOnIndex($request, $resource) && $this->canResolveDisplayValue();
    }

    #[\Override]
    public function isShownOnDetail(NovaRequest $request, $resource): bool
    {
        return parent::isShownOnDetail($request, $resource) && $this->canResolveDisplayValue();
    }

    public function canResolveDisplayValue(): bool
    {
        return $this->labelPath !== null
            || $this->labelCallback !== null
            || $this->displayCallback !== null
            || $this->resolveLabelFromOptions
            || $this->optionSource()->isCheapToLabel();
    }

    /**
     * Resolve the field for display, labelling the stored value.
     *
     * A user-supplied displayUsing() callback still wins: Nova handles that
     * branch itself.
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
     * Per-request label memo, shared across every row of an index page.
     *
     * @var array<string, array<string, string|null>>
     */
    protected static array $labelMemo = [];

    public static function flushLabelMemo(): void
    {
        static::$labelMemo = [];
    }
}
