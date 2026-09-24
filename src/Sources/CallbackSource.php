<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Sources;

use Closure;
use Gabrielesbaiz\NovaAjaxSelect\Contracts\OptionSource;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Support\OptionCollection;
use Laravel\Nova\Http\Requests\NovaRequest;
use ReflectionFunction;

/**
 * The callback is invoked as ($context, $request).
 */
final class CallbackSource implements OptionSource
{
    /**
     * The memoized option set for the current context.
     */
    private ?OptionCollection $resolved = null;

    /**
     * The memoized, unfiltered option set.
     */
    private ?OptionCollection $unsearched = null;

    /**
     * Create a new callback source instance.
     *
     * @param  callable(AjaxSelectContext, NovaRequest): mixed  $callback
     * @param  (callable(string, AjaxSelectContext): mixed)|null  $searchCallback
     */
    public function __construct(
        private $callback,
        private $searchCallback = null,
    ) {}

    /**
     * Resolve the limited, optionally searched option set.
     */
    public function resolve(AjaxSelectContext $context): OptionCollection
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        if ($context->isSearching() && $this->searchCallback !== null) {
            $options = OptionCollection::make(($this->searchCallback)($context->search, $context));

            return $this->resolved = $options->take($context->limit ?: null);
        }

        // Without a dedicated search callback, resolve once and filter in PHP.
        return $this->resolved = $this->resolveAll($context)
            ->search($context->search)
            ->take($context->limit ?: null);
    }

    /**
     * Determine if the given value is a selectable option.
     */
    public function contains(mixed $value, AjaxSelectContext $context): bool
    {
        return $this->resolveAll($context)->has($value);
    }

    /**
     * Resolve the label for a single value without materializing every option.
     */
    public function label(mixed $value, AjaxSelectContext $context): ?string
    {
        return $this->resolveAll($context)->labelFor($value);
    }

    /**
     * Get the stable identifier used to build cache keys for this source.
     */
    public function signature(): string
    {
        if (! $this->callback instanceof Closure) {
            return 'callback:'.(is_string($this->callback) ? $this->callback : get_debug_type($this->callback));
        }

        // File and line keep two closures on the same attribute from sharing a key.
        $reflection = new ReflectionFunction($this->callback);

        return 'callback:'.$reflection->getFileName().':'.$reflection->getStartLine();
    }

    /**
     * Determine if labelling a value is cheap enough to do per index row.
     */
    public function isCheapToLabel(): bool
    {
        return false;
    }

    /**
     * Resolve the unfiltered option set, memoized for the life of the source.
     */
    private function resolveAll(AjaxSelectContext $context): OptionCollection
    {
        return $this->unsearched ??= OptionCollection::make(($this->callback)($context, $context->request));
    }
}
