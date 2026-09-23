<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * An ordered, normalized set of options.
 *
 * @implements IteratorAggregate<int, Option>
 */
final class OptionCollection implements \Countable, IteratorAggregate
{
    /**
     * @param  array<int, Option>  $options
     */
    private function __construct(private array $options = []) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * Normalize any supported payload into Nova's `{label, value}` shape.
     *
     * Accepts value => label maps, lists of option arrays (including the legacy
     * `{value, display}` shape), models, enums and scalars.
     */
    public static function make(mixed $options): self
    {
        if ($options === null) {
            return self::empty();
        }

        if ($options instanceof self) {
            return $options;
        }

        // Enum class-string: `optionsFromEnum()` normally handles this, but an
        // `options(SomeEnum::class)` call should not silently produce nothing.
        if (is_string($options) && enum_exists($options)) {
            $options = $options::cases();
        }

        if ($options instanceof Arrayable) {
            $options = $options->toArray();
        }

        if ($options instanceof Traversable) {
            $options = iterator_to_array($options);
        }

        if (! is_iterable($options)) {
            $options = [$options];
        }

        $normalized = [];

        foreach ($options as $key => $option) {
            // A list of option arrays carries its own value; only associative
            // `value => label` maps may use the key.
            $option = Option::make($option, is_int($key) && is_array($option) ? null : $key);

            if ($option !== null) {
                $normalized[] = $option;
            }
        }

        return new self($normalized);
    }

    public function take(?int $limit): self
    {
        if ($limit === null || $limit <= 0 || count($this->options) <= $limit) {
            return $this;
        }

        return new self(array_slice($this->options, 0, $limit));
    }

    /**
     * Filter in PHP. Only used by sources that cannot push the search into SQL.
     */
    public function search(?string $search): self
    {
        if ($search === null || $search === '') {
            return $this;
        }

        $needle = mb_strtolower($search);

        return new self(array_values(array_filter(
            $this->options,
            static fn (Option $option): bool => str_contains(mb_strtolower($option->label), $needle)
        )));
    }

    public function has(mixed $value): bool
    {
        return $this->find($value) !== null;
    }

    public function find(mixed $value): ?Option
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) Option::castValue($value);

        foreach ($this->options as $option) {
            if ((string) $option->value === $value) {
                return $option;
            }
        }

        return null;
    }

    public function labelFor(mixed $value): ?string
    {
        return $this->find($value)?->label;
    }

    /**
     * Ensure the given option is present, prepending it when missing, so a
     * stored value never disappears from a searched or paginated list.
     */
    public function prepend(?Option $option): self
    {
        if ($option === null || $this->has($option->value)) {
            return $this;
        }

        return new self([$option, ...$this->options]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function serialize(): array
    {
        return array_map(static fn (Option $option): array => $option->toArray(), $this->options);
    }

    /**
     * @return array<int, string|int>
     */
    public function values(): array
    {
        return array_map(static fn (Option $option): string|int => $option->value, $this->options);
    }

    /**
     * @return Collection<int, Option>
     */
    public function collect(): Collection
    {
        return new Collection($this->options);
    }

    public function isEmpty(): bool
    {
        return $this->options === [];
    }

    public function count(): int
    {
        return count($this->options);
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->options);
    }
}
