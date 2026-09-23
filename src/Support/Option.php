<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Stringable;

/**
 * A single, already normalized option in Nova's `{label, value}` shape.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Option implements Arrayable
{
    public function __construct(
        public string|int $value,
        public string $label,
        public ?string $group = null,
        public ?string $subtitle = null,
        public bool $disabled = false,
    ) {}

    /**
     * Build an option from any of the shapes this package has ever accepted.
     *
     * Supported: scalars, `{value|id, label|display|title|name|text}` arrays,
     * Eloquent models, Arrayable, BackedEnum and Stringable.
     */
    public static function make(mixed $option, string|int|null $key = null): ?self
    {
        if ($option === null) {
            return null;
        }

        if ($option instanceof self) {
            return $option;
        }

        if ($option instanceof \BackedEnum) {
            return new self(self::castValue($option->value), (string) $option->name);
        }

        if ($option instanceof Model) {
            $option = $option->toArray();
        } elseif ($option instanceof Arrayable) {
            $option = $option->toArray();
        } elseif ($option instanceof Stringable || $option instanceof Stringable) {
            $option = (string) $option;
        }

        // `['it' => 'Italy']` — the key carries the value, the item the label.
        if (! is_array($option)) {
            return $key !== null
                ? new self(self::castValue($key), (string) $option)
                : new self(self::castValue($option), (string) $option);
        }

        $value = $option['value'] ?? $option['id'] ?? $key;

        if ($value === null) {
            return null;
        }

        // `display` is the legacy 1.x / alexwenzel key; `label` always wins.
        $label = $option['label']
            ?? $option['display']
            ?? $option['title']
            ?? $option['name']
            ?? $option['text']
            ?? $value;

        return new self(
            value: self::castValue($value),
            label: (string) $label,
            group: isset($option['group']) ? (string) $option['group'] : null,
            subtitle: isset($option['subtitle']) ? (string) $option['subtitle'] : null,
            disabled: (bool) ($option['disabled'] ?? false),
        );
    }

    /**
     * Cast numeric strings to integers, mirroring Nova's own Select field so
     * strict comparisons against integer foreign keys keep working.
     */
    public static function castValue(mixed $value): string|int
    {
        if (is_int($value)) {
            return $value;
        }

        if ($value instanceof \BackedEnum) {
            return self::castValue($value->value);
        }

        $value = (string) $value;

        return preg_match('/^-?\d+$/', $value) === 1 && $value === (string) (int) $value
            ? (int) $value
            : $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'value' => $this->value,
            'label' => $this->label,
            'group' => $this->group,
            'subtitle' => $this->subtitle,
            'disabled' => $this->disabled ?: null,
        ], static fn ($item) => $item !== null);
    }
}
