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
 * A backed enum. Labels default to Nova's own humanization, matching the
 * behaviour of Nova's Select field.
 */
final class EnumSource implements OptionSource
{
    /**
     * @param  class-string<BackedEnum>  $enum
     * @param  (Closure(BackedEnum): string)|null  $label
     * @param  (Closure(BackedEnum): bool)|null  $filter
     */
    public function __construct(
        private readonly string $enum,
        private readonly ?Closure $label = null,
        private readonly ?Closure $filter = null,
    ) {}

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

    public function contains(mixed $value, AjaxSelectContext $context): bool
    {
        $case = $this->enum::tryFrom(is_int($value) ? $value : (string) $value);

        if ($case === null) {
            return false;
        }

        return $this->filter === null || (bool) ($this->filter)($case);
    }

    public function label(mixed $value, AjaxSelectContext $context): ?string
    {
        $case = $this->enum::tryFrom(is_int($value) ? $value : (string) $value);

        if ($case === null) {
            return null;
        }

        return $this->label !== null ? ($this->label)($case) : Nova::humanize($case);
    }

    public function signature(): string
    {
        return 'enum:'.$this->enum;
    }

    public function isCheapToLabel(): bool
    {
        return true;
    }
}
