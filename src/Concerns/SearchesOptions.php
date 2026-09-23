<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Concerns;

use Closure;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Server-side search.
 *
 * The browser sends the search term inside Nova's own field-sync request, so
 * no package-owned route is involved.
 */
trait SearchesOptions
{
    protected ?bool $asyncSearchable = null;

    /** @var (Closure(string, AjaxSelectContext): mixed)|null */
    protected ?Closure $searchResolver = null;

    protected ?int $minSearchLength = null;

    protected ?int $optionsLimit = null;

    /**
     * Load options from the server as the user types.
     *
     * Also flips Nova's own `searchable` flag so the field renders Nova's
     * SearchInput instead of a plain select.
     */
    public function asyncSearchable(callable|bool $searchable = true): static
    {
        $this->asyncSearchable = is_bool($searchable) ? $searchable : (bool) $searchable(app(NovaRequest::class));

        return $this->searchable($this->asyncSearchable);
    }

    /**
     * Provide a dedicated search callback, invoked as `($search, $context)`.
     *
     * Kept separate from the option source so a model-backed field does not
     * lose its cheap contains()/label() lookups.
     */
    public function searchUsing(Closure $callback): static
    {
        $this->searchResolver = $callback;

        return $this->asyncSearchable();
    }

    public function minSearchLength(int $length): static
    {
        $this->minSearchLength = max(0, $length);

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->optionsLimit = max(0, $limit);

        return $this;
    }

    public function isAsyncSearchable(): bool
    {
        return $this->asyncSearchable ?? false;
    }

    public function minimumSearchLength(): int
    {
        return $this->minSearchLength ?? (int) config('nova-ajax-select.search.min_length', 0);
    }

    public function optionsLimit(): int
    {
        return $this->optionsLimit ?? (int) config('nova-ajax-select.search.limit', 50);
    }

    protected function searchIsLongEnough(AjaxSelectContext $context): bool
    {
        $minimum = $this->minimumSearchLength();

        if ($minimum === 0) {
            return true;
        }

        return $context->isSearching() && mb_strlen($context->search ?? '') >= $minimum;
    }
}
