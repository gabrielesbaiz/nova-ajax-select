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
 * Optional caching of resolved option sets.
 *
 * Caching is opt-in per field. Remember that the cache is NOT scoped to the
 * authenticated user or tenant by default - scoping it that way would destroy
 * the hit rate. If your options depend on who is asking, set `cacheScope()`.
 */
trait CachesOptions
{
    protected DateTimeInterface|DateInterval|int|null $cacheTtl = null;

    protected ?string $cacheStore = null;

    /** @var array<int, string> */
    protected array $cacheTags = [];

    /** @var (Closure(AjaxSelectContext): mixed)|array<array-key, mixed>|null */
    protected $cacheScope = null;

    protected bool $cacheSearchResults = false;

    public function cacheFor(DateTimeInterface|DateInterval|int $ttl, ?string $store = null): static
    {
        $this->cacheTtl = $ttl;
        $this->cacheStore = $store;

        return $this;
    }

    /**
     * Add anything the option set depends on but the key cannot infer -
     * the current tenant, for instance.
     *
     * @param  (Closure(AjaxSelectContext): mixed)|array<array-key, mixed>  $scope
     */
    public function cacheScope(Closure|array $scope): static
    {
        $this->cacheScope = $scope;

        return $this;
    }

    /**
     * @param  array<int, string>  $tags
     */
    public function cacheTags(array $tags): static
    {
        $this->cacheTags = $tags;

        return $this;
    }

    /**
     * Cache search results too. Off by default: an indexed LIKE usually beats
     * one cache entry per keystroke.
     */
    public function cacheSearchResults(bool $cache = true): static
    {
        $this->cacheSearchResults = $cache;

        return $this;
    }

    public function withoutCache(): static
    {
        $this->cacheTtl = 0;

        return $this;
    }

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
