<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Concerns;

use Closure;
use DateInterval;
use DateTimeInterface;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Support\OptionCollection;
use Illuminate\Support\Facades\Cache;

/**
 * Cache keys are not scoped to the authenticated user or tenant; use cacheScope() for that.
 */
trait CachesOptions
{
    /**
     * The time to live for cached option sets.
     */
    protected DateTimeInterface|DateInterval|int|null $cacheTtl = null;

    /**
     * The cache store that should be used.
     */
    protected ?string $cacheStore = null;

    /**
     * The tags that should be applied to the cache entry.
     *
     * @var array<int, string>
     */
    protected array $cacheTags = [];

    /**
     * The additional values that should be mixed into the cache key.
     *
     * @var (Closure(AjaxSelectContext): mixed)|array<array-key, mixed>|null
     */
    protected $cacheScope = null;

    /**
     * Indicates if search results should be cached.
     */
    protected bool $cacheSearchResults = false;

    /**
     * Cache the resolved option set for the given duration.
     */
    public function cacheFor(DateTimeInterface|DateInterval|int $ttl, ?string $store = null): static
    {
        $this->cacheTtl = $ttl;
        $this->cacheStore = $store;

        return $this;
    }

    /**
     * Mix additional values, such as the current tenant, into the cache key.
     *
     * @param  (Closure(AjaxSelectContext): mixed)|array<array-key, mixed>  $scope
     */
    public function cacheScope(Closure|array $scope): static
    {
        $this->cacheScope = $scope;

        return $this;
    }

    /**
     * Set the tags that should be applied to the cache entry.
     *
     * @param  array<int, string>  $tags
     */
    public function cacheTags(array $tags): static
    {
        $this->cacheTags = $tags;

        return $this;
    }

    /**
     * Cache search results in addition to the unsearched option set.
     */
    public function cacheSearchResults(bool $cache = true): static
    {
        $this->cacheSearchResults = $cache;

        return $this;
    }

    /**
     * Disable caching for the field.
     */
    public function withoutCache(): static
    {
        $this->cacheTtl = 0;

        return $this;
    }

    /**
     * Get the cache key for the option set resolved in the given context.
     */
    public function optionsCacheKey(AjaxSelectContext $context): string
    {
        return (string) config('nova-ajax-select.cache.prefix', 'nova-ajax-select').':'.hash('xxh128', json_encode([
            'source' => $this->optionSource()->signature(),
            'field' => $this->dependentComponentKey(),
            'parents' => $context->parents,
            'search' => $this->cacheSearchResults ? $context->search : null,
            'limit' => $context->limit,
            'locale' => app()->getLocale(),
            'scope' => $this->cacheScope instanceof Closure ? ($this->cacheScope)($context) : $this->cacheScope,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Resolve the option set through the cache when one is configured.
     *
     * @param  Closure(): OptionCollection  $resolve
     */
    protected function rememberOptions(AjaxSelectContext $context, string $key, Closure $resolve): OptionCollection
    {
        $ttl = $this->cacheTtl ?? (config('nova-ajax-select.cache.enabled', false)
            ? config('nova-ajax-select.cache.ttl')
            : null);

        if ($ttl === null || $ttl === 0) {
            return $resolve();
        }

        // A search that is not explicitly cached always goes to the source.
        if ($context->isSearching() && ! $this->cacheSearchResults) {
            return $resolve();
        }

        $store = Cache::store($this->cacheStore ?? config('nova-ajax-select.cache.store'));

        if ($this->cacheTags !== []) {
            $store = $store->tags($this->cacheTags);
        }

        $cached = $store->remember($key, $ttl, static fn (): array => $resolve()->serialize());

        return OptionCollection::make($cached);
    }
}
