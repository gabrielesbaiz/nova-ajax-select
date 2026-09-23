<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Concerns;

use Laravel\Nova\Fields\Dependent;
use Laravel\Nova\Fields\Field;

/**
 * The `parent()` API, implemented on top of Nova's native dependent fields.
 */
trait HasParentAttributes
{
    /** @var array<int, string> */
    protected array $parentAttributes = [];

    protected ?Dependent $ajaxDependent = null;

    protected bool $clearsWhenParentChanges = true;

    protected bool $emptyWhenParentMissing = true;

    /**
     * Watch one or more parent attributes and reload options when they change.
     */
    public function parent(Field|string ...$attributes): static
    {
        $this->parentAttributes = array_values(array_unique([
            ...$this->parentAttributes,
            ...array_map(
                static fn (Field|string $attribute): string => $attribute instanceof Field
                    ? $attribute->attribute
                    : $attribute,
                $attributes
            ),
        ]));

        // Dependent::$attributes is public, so mutating the single owned
        // Dependent in place keeps repeated parent() calls from registering
        // duplicate dependencies (and duplicating the sync work).
        if ($this->ajaxDependent !== null) {
            $this->ajaxDependent->attributes = $this->parentAttributes;
        }

        return $this;
    }

    /**
     * @param  array<int, Field|string>  $attributes
     */
    public function parents(array $attributes): static
    {
        return $this->parent(...$attributes);
    }

    /**
     * @return array<int, string>
     */
    public function parentAttributes(): array
    {
        return $this->parentAttributes;
    }

    public function hasParents(): bool
    {
        return $this->parentAttributes !== [];
    }

    /**
     * Clear the selected value when it is no longer among the options.
     */
    public function clearWhenParentChanges(bool $clear = true): static
    {
        $this->clearsWhenParentChanges = $clear;

        return $this;
    }

    public function clearsWhenParentChanges(): bool
    {
        return $this->clearsWhenParentChanges;
    }

    /**
     * Resolve no options at all while a parent is still empty.
     *
     * On by default: without it, a dependent query would run unbounded the
     * first time the form renders.
     */
    public function emptyWhenParentMissing(bool $empty = true): static
    {
        $this->emptyWhenParentMissing = $empty;

        return $this;
    }
}
