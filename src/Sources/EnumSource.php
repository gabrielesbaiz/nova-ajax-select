<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Sources;

use BackedEnum;
use Closure;
use Gabrielesbaiz\NovaAjaxSelect\Contracts\OptionSource;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Support\Option;
use Gabrielesbaiz\NovaAjaxSelect\Support\OptionCollection;
use Laravel\Nova\Nova;

/**
 * Labels default to Nova's own humanization, matching Nova's Select field.
 */
final class EnumSource implements OptionSource
{
    /**
     * Create a new enum source instance.
     *
     * @param  class-string<BackedEnum>  $enum
     * @param  (Closure(BackedEnum): string)|null  $label
     * @param  (Closure(BackedEnum): bool)|null  $filter
     */
    public function __construct(
        private readonly string $enum,
        private readonly ?Closure $label = null,
        private readonly ?Closure $filter = null,
    ) {}

    /**
     * Resolve the limited, optionally searched option set.
     */
    public function resolve(AjaxSelectContext $context): OptionCollection
    {
        $cases = $this->enum::cases();

        if ($this->filter !== null) {
            $cases = array_values(array_filter($cases, $this->filter));
        }

        $options = array_map(fn (BackedEnum $case): Option => new Option(
            value: Option::castValue($case->value),
            label: $this->label !== null ? ($this->label)($case) : Nova::humanize($case),
        ), $cases);

        return OptionCollection::make($options)->search($context->search)->take($context->limit ?: null);
    }

    /**
     * Determine if the given value is a selectable option.
     */
    public function contains(mixed $value, AjaxSelectContext $context): bool
    {
        $case = $this->enum::tryFrom(is_int($value) ? $value : (string) $value);

        if ($case === null) {
            return false;
        }

        return $this->filter === null || (bool) ($this->filter)($case);
    }

    /**
     * Resolve the label for a single value without materializing every option.
     */
    public function label(mixed $value, AjaxSelectContext $context): ?string
    {
        $case = $this->enum::tryFrom(is_int($value) ? $value : (string) $value);

        if ($case === null) {
            return null;
        }

        return $this->label !== null ? ($this->label)($case) : Nova::humanize($case);
    }

    /**
     * Get the stable identifier used to build cache keys for this source.
     */
    public function signature(): string
    {
        return 'enum:'.$this->enum;
    }

    /**
     * Determine if labelling a value is cheap enough to do per index row.
     */
    public function isCheapToLabel(): bool
    {
        return true;
    }
}
