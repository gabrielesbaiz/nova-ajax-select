<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Sources;

use Closure;
use Gabrielesbaiz\NovaAjaxSelect\Contracts\OptionSource;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Support\OptionCollection;

/**
 * The legacy 1.x mode: the browser fetches a URL the application owns.
 *
 * The server cannot know the option set here, so this source resolves to
 * nothing, always reports containment (there is nothing to validate against)
 * and cannot label a value - which is what keeps the field off index and
 * detail unless an explicit label strategy is configured.
 *
 * @deprecated 2.0 Use options() or optionsFromModel(). Removed in 3.0.
 */
final class EndpointSource implements OptionSource
{
    /**
     * @param  (Closure(mixed): mixed)|null  $transform
     */
    public function __construct(
        public readonly string $url,
        public readonly ?Closure $transform = null,
    ) {}

    public function resolve(AjaxSelectContext $context): OptionCollection
    {
        return OptionCollection::empty();
    }

    public function contains(mixed $value, AjaxSelectContext $context): bool
    {
        return true;
    }

    public function label(mixed $value, AjaxSelectContext $context): ?string
    {
        return null;
    }

    public function signature(): string
    {
        return 'endpoint:'.$this->url;
    }

    public function isCheapToLabel(): bool
    {
        return false;
    }
}
