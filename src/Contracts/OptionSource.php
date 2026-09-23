<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Contracts;

use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Support\OptionCollection;

/**
 * A source of options for an AjaxSelect field.
 *
 * The three read methods are deliberately separate: resolving the full option
 * set is the expensive path, so validation asks only `contains()` and
 * index/detail rendering asks only `label()`.
 */
interface OptionSource
{
    /**
     * Resolve the full (limited, optionally searched) option set.
     */
    public function resolve(AjaxSelectContext $context): OptionCollection;

    /**
     * Determine whether the given value is a selectable option.
     */
    public function contains(mixed $value, AjaxSelectContext $context): bool;

    /**
     * Resolve the label for a single value, without materializing every option.
     */
    public function label(mixed $value, AjaxSelectContext $context): ?string;

    /**
     * A stable identifier for this source, used to build cache keys.
     */
    public function signature(): string;

    /**
     * Determine whether labelling a value is cheap enough to do per index row.
     */
    public function isCheapToLabel(): bool;
}
