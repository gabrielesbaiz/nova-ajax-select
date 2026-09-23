<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Sources;

use Gabrielesbaiz\NovaAjaxSelect\Contracts\OptionSource;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Support\OptionCollection;

/**
 * A static, already-known option set. Free to resolve, search and label.
 */
final class ArraySource implements OptionSource
{
    private OptionCollection $options;

    public function __construct(mixed $options)
    {
        $this->options = OptionCollection::make($options);
    }

    public function resolve(AjaxSelectContext $context): OptionCollection
    {
        return $this->options->search($context->search)->take($context->limit ?: null);
    }

    public function contains(mixed $value, AjaxSelectContext $context): bool
    {
        return $this->options->has($value);
    }

    public function label(mixed $value, AjaxSelectContext $context): ?string
    {
        return $this->options->labelFor($value);
    }

    public function signature(): string
    {
        return 'array:'.md5(serialize($this->options->serialize()));
    }

    public function isCheapToLabel(): bool
    {
        return true;
    }
}
