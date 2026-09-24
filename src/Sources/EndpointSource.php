<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Sources;

use Closure;
use Gabrielesbaiz\NovaAjaxSelect\Contracts\OptionSource;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Support\OptionCollection;

/**
 * The browser fetches the option set from a route the application owns, so this
 * source resolves to nothing, always reports containment and cannot label a value.
 *
 * @deprecated 2.0 Use options() or optionsFromModel(). Removed in 3.0.
 */
final class EndpointSource implements OptionSource
{
    /**
     * Create a new endpoint source instance.
     *
     * @param  (Closure(mixed): mixed)|null  $transform
     */
    public function __construct(
        public readonly string $url,
        public readonly ?Closure $transform = null,
    ) {}

    /**
     * Resolve the limited, optionally searched option set.
     */
    public function resolve(AjaxSelectContext $context): OptionCollection
    {
        return OptionCollection::empty();
    }

    /**
     * Determine if the given value is a selectable option.
     */
    public function contains(mixed $value, AjaxSelectContext $context): bool
    {
        return true;
    }

    /**
     * Resolve the label for a single value without materializing every option.
     */
    public function label(mixed $value, AjaxSelectContext $context): ?string
    {
        return null;
    }

    /**
     * Get the stable identifier used to build cache keys for this source.
     */
    public function signature(): string
    {
        return 'endpoint:'.$this->url;
    }

    /**
     * Determine if labelling a value is cheap enough to do per index row.
     */
    public function isCheapToLabel(): bool
    {
        return false;
    }
}
