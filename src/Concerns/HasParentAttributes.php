<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Concerns;

use Illuminate\Support\Str;
use Laravel\Nova\Fields\Dependent;
use Laravel\Nova\Fields\Field;

trait HasParentAttributes
{
    /**
     * The attributes the field's options depend on.
     *
     * @var array<int, string>
     */
    protected array $parentAttributes = [];

    /**
     * The labels used when naming a parent to the user.
     *
     * @var array<string, string>
     */
    protected array $parentLabels = [];

    /**
     * The dependent instance owned by the field.
     */
    protected ?Dependent $ajaxDependent = null;

    /**
     * Indicates if the value should be cleared when a parent changes.
     */
    protected bool $clearsWhenParentChanges = true;

    /**
     * Indicates if the options should be empty while a parent is missing.
     */
    protected bool $emptyWhenParentMissing = true;

    /**
     * Watch one or more parent attributes and reload options when they change.
     */
    public function parent(Field|string ...$attributes): static
    {
        foreach ($attributes as $attribute) {
            if ($attribute instanceof Field) {
                $this->parentLabels[$attribute->attribute] = $attribute->name;
            }
        }

        $this->parentAttributes = array_values(array_unique([
            ...$this->parentAttributes,
            ...array_map(
                static fn (Field|string $attribute): string => $attribute instanceof Field
                    ? $attribute->attribute
                    : $attribute,
                $attributes
            ),
        ]));

        // Mutated in place so repeated parent() calls do not register duplicate dependencies.
        if ($this->ajaxDependent !== null) {
            $this->ajaxDependent->attributes = $this->parentAttributes;
        }

        return $this;
    }

    /**
     * Watch the given parent attributes and reload options when they change.
     *
     * @param  array<int, Field|string>  $attributes
     */
    public function parents(array $attributes): static
    {
        return $this->parent(...$attributes);
    }

    /**
     * Set the label used when naming a parent to the user.
     *
     * @param  string|array<string, string>  $label
     */
    public function parentLabel(string|array $label): static
    {
        if (is_array($label)) {
            $this->parentLabels = array_merge($this->parentLabels, $label);

            return $this;
        }

        $first = $this->parentAttributes[0] ?? null;

        if ($first !== null) {
            $this->parentLabels[$first] = $label;
        }

        return $this;
    }

    /**
     * Get the label for each parent attribute.
     *
     * Falls back to a readable form of the attribute so the field never shows
     * a raw column name to the user.
     *
     * @return array<string, string>
     */
    public function parentLabels(): array
    {
        $labels = [];

        foreach ($this->parentAttributes as $attribute) {
            $labels[$attribute] = $this->parentLabels[$attribute]
                ?? Str::headline((string) Str::of($attribute)->beforeLast('_id'));
        }

        return $labels;
    }

    /**
     * Get the parent attributes the field watches.
     *
     * @return array<int, string>
     */
    public function parentAttributes(): array
    {
        return $this->parentAttributes;
    }

    /**
     * Determine if the field watches any parent attributes.
     */
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

    /**
     * Determine if the value is cleared when a parent changes.
     */
    public function clearsWhenParentChanges(): bool
    {
        return $this->clearsWhenParentChanges;
    }

    /**
     * Resolve no options at all while a parent is still empty.
     */
    public function emptyWhenParentMissing(bool $empty = true): static
    {
        $this->emptyWhenParentMissing = $empty;

        return $this;
    }
}
