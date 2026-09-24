<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, Option>
 */
final class OptionCollection implements \Countable, IteratorAggregate
{
    /**
     * Create a new option collection.
     *
     * @param  array<int, Option>  $options
     */
    private function __construct(private array $options = []) {}

    /**
     * Create a new empty option collection.
     */
    public static function empty(): self
    {
        return new self;
    }

    /**
     * Create a new option collection from the given payload.
     *
     * Accepts "value => label" maps, lists of option arrays, models, enums and scalars.
     */
    public static function make(mixed $options): self
    {
        if ($options === null) {
            return self::empty();
        }

        if ($options instanceof self) {
            return $options;
        }

        // An options(SomeEnum::class) call should not silently produce nothing.
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
            // A list of option arrays carries its own value; only a
            // "value => label" map may use the key.
            $option = Option::make($option, is_int($key) && is_array($option) ? null : $key);

            if ($option !== null) {
                $normalized[] = $option;
            }
        }

        return new self($normalized);
    }

    /**
     * Take the first given number of options.
     */
    public function take(?int $limit): self
    {
        if ($limit === null || $limit <= 0 || count($this->options) <= $limit) {
            return $this;
        }

        return new self(array_slice($this->options, 0, $limit));
    }

    /**
     * Filter the options by label, in PHP.
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

    /**
     * Determine if the collection contains the given value.
     */
    public function has(mixed $value): bool
    {
        return $this->find($value) !== null;
    }

    /**
     * Get the option matching the given value.
     */
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

    /**
     * Get the label for the given value.
     */
    public function labelFor(mixed $value): ?string
    {
        return $this->find($value)?->label;
    }

    /**
     * Prepend the given option when it is not already present.
     */
    public function prepend(?Option $option): self
    {
        if ($option === null || $this->has($option->value)) {
            return $this;
        }

        return new self([$option, ...$this->options]);
    }

    /**
     * Serialize the options for the field.
     *
     * @return array<int, array<string, mixed>>
     */
    public function serialize(): array
    {
        return array_map(static fn (Option $option): array => $option->toArray(), $this->options);
    }

    /**
     * Get the value of every option.
     *
     * @return array<int, string|int>
     */
    public function values(): array
    {
        return array_map(static fn (Option $option): string|int => $option->value, $this->options);
    }

    /**
     * Get the options as a collection.
     *
     * @return Collection<int, Option>
     */
    public function collect(): Collection
    {
        return new Collection($this->options);
    }

    /**
     * Determine if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return $this->options === [];
    }

    /**
     * Count the options in the collection.
     */
    public function count(): int
    {
        return count($this->options);
    }

    /**
     * Get an iterator for the options.
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->options);
    }
}
