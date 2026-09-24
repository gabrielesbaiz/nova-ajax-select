<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Contracts;

use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Support\OptionCollection;

interface OptionSource
{
    /**
     * Resolve the limited, optionally searched option set.
     */
    public function resolve(AjaxSelectContext $context): OptionCollection;

    /**
     * Determine if the given value is a selectable option.
     */
    public function contains(mixed $value, AjaxSelectContext $context): bool;

    /**
     * Resolve the label for a single value without materializing every option.
     */
    public function label(mixed $value, AjaxSelectContext $context): ?string;

    /**
     * Get the stable identifier used to build cache keys for this source.
     */
    public function signature(): string;

    /**
     * Determine if labelling a value is cheap enough to do per index row.
     */
    public function isCheapToLabel(): bool;
}
