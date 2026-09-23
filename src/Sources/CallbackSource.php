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
 * A user-supplied closure.
 *
 * The callback is invoked as `($context, $request)`; PHP tolerates extra
 * arguments, so `fn () =>`, `fn ($c) =>` and `fn ($c, $r) =>` all work.
 */
final class CallbackSource implements OptionSource
{
    private ?OptionCollection $resolved = null;

    private ?OptionCollection $unsearched = null;

    /**
     * @param  callable(AjaxSelectContext, NovaRequest): mixed  $callback
     * @param  (callable(string, AjaxSelectContext): mixed)|null  $searchCallback
     */
    public function __construct(
        private $callback,
        private $searchCallback = null,
    ) {}

    public function resolve(AjaxSelectContext $context): OptionCollection
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        if ($context->isSearching() && $this->searchCallback !== null) {
            $options = OptionCollection::make(($this->searchCallback)($context->search, $context));

            return $this->resolved = $options->take($context->limit ?: null);
        }

        // No dedicated search callback: resolve once, then filter in PHP.
        return $this->resolved = $this->resolveAll($context)
            ->search($context->search)
            ->take($context->limit ?: null);
    }

    public function contains(mixed $value, AjaxSelectContext $context): bool
    {
        return $this->resolveAll($context)->has($value);
    }

    public function label(mixed $value, AjaxSelectContext $context): ?string
    {
        return $this->resolveAll($context)->labelFor($value);
    }

    public function signature(): string
    {
        if (! $this->callback instanceof Closure) {
            return 'callback:'.(is_string($this->callback) ? $this->callback : get_debug_type($this->callback));
        }

        // File + line keeps two different closures on the same attribute from
        // sharing a cache key.
        $reflection = new ReflectionFunction($this->callback);

        return 'callback:'.$reflection->getFileName().':'.$reflection->getStartLine();
    }

    public function isCheapToLabel(): bool
    {
        return false;
    }

    /**
     * Resolve the unfiltered option set, memoized for the life of the field.
     */
    private function resolveAll(AjaxSelectContext $context): OptionCollection
    {
        return $this->unsearched ??= OptionCollection::make(($this->callback)($context, $context->request));
    }
}
