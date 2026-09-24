<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Concerns;

use Closure;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Laravel\Nova\Http\Requests\NovaRequest;

trait SearchesOptions
{
    /**
     * Indicates if options are loaded from the server as the user types.
     */
    protected ?bool $asyncSearchable = null;

    /**
     * The callback used to resolve search results.
     *
     * @var (Closure(string, AjaxSelectContext): mixed)|null
     */
    protected ?Closure $searchResolver = null;

    /**
     * The minimum number of characters before a search is performed.
     */
    protected ?int $minSearchLength = null;

    /**
     * The maximum number of options that should be resolved.
     */
    protected ?int $optionsLimit = null;

    /**
     * Load options from the server as the user types.
     *
     * Also sets Nova's searchable flag so the field renders a search input.
     */
    public function asyncSearchable(callable|bool $searchable = true): static
    {
        $this->asyncSearchable = is_bool($searchable) ? $searchable : (bool) $searchable(app(NovaRequest::class));

        return $this->searchable($this->asyncSearchable);
    }

    /**
     * Resolve search results using the given callback.
     *
     * The callback is invoked as ($search, $context) and leaves the option
     * source's single-row lookups in place.
     */
    public function searchUsing(Closure $callback): static
    {
        $this->searchResolver = $callback;

        return $this->asyncSearchable();
    }

    /**
     * Set the minimum number of characters before a search is performed.
     */
    public function minSearchLength(int $length): static
    {
        $this->minSearchLength = max(0, $length);

        return $this;
    }

    /**
     * Set the maximum number of options that should be resolved.
     */
    public function limit(int $limit): static
    {
        $this->optionsLimit = max(0, $limit);

        return $this;
    }

    /**
     * Determine if options are loaded from the server as the user types.
     */
    public function isAsyncSearchable(): bool
    {
        return $this->asyncSearchable ?? false;
    }

    /**
     * Get the minimum number of characters before a search is performed.
     */
    public function minimumSearchLength(): int
    {
        return $this->minSearchLength ?? (int) config('nova-ajax-select.search.min_length', 0);
    }

    /**
     * Get the maximum number of options that should be resolved.
     */
    public function optionsLimit(): int
    {
        return $this->optionsLimit ?? (int) config('nova-ajax-select.search.limit', 50);
    }

    /**
     * Determine if the search term meets the minimum length.
     */
    protected function searchIsLongEnough(AjaxSelectContext $context): bool
    {
        $minimum = $this->minimumSearchLength();

        if ($minimum === 0) {
            return true;
        }

        return $context->isSearching() && mb_strlen($context->search ?? '') >= $minimum;
    }
}
