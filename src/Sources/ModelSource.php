<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Sources;

use Closure;
use Gabrielesbaiz\NovaAjaxSelect\Contracts\OptionSource;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Support\Option;
use Gabrielesbaiz\NovaAjaxSelect\Support\OptionCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An Eloquent model.
 *
 * Search is pushed into SQL and `contains()` / `label()` run single-row
 * queries, so neither validation nor index rendering ever materializes the
 * full option set.
 */
final class ModelSource implements OptionSource
{
    /**
     * @param  class-string<Model>  $model
     * @param  (Closure(Builder, AjaxSelectContext): mixed)|null  $query
     * @param  array<int, string>  $searchColumns
     */
    public function __construct(
        private readonly string $model,
        private readonly string $labelColumn = 'name',
        private readonly ?string $valueColumn = null,
        private readonly ?Closure $query = null,
        private array $searchColumns = [],
    ) {}

    /**
     * @param  array<int, string>  $columns
     */
    public function withSearchColumns(array $columns): self
    {
        $this->searchColumns = $columns;

        return $this;
    }

    public function resolve(AjaxSelectContext $context): OptionCollection
    {
        $query = $this->newQuery($context);

        $this->applySearch($query, $context->search);

        if ($context->limit > 0) {
            $query->limit($context->limit);
        }

        $value = $this->valueColumn($query->getModel());

        return OptionCollection::make(
            $query->get()->map(fn (Model $model): Option => new Option(
                value: Option::castValue($model->getAttribute($value)),
                label: (string) $model->getAttribute($this->labelColumn),
            ))->all()
        );
    }

    public function contains(mixed $value, AjaxSelectContext $context): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $query = $this->newQuery($context);

        return $query->where($this->valueColumn($query->getModel()), $value)->exists();
    }

    public function label(mixed $value, AjaxSelectContext $context): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $query = $this->newQuery($context);

        $label = $query->where($this->valueColumn($query->getModel()), $value)->value($this->labelColumn);

        return $label === null ? null : (string) $label;
    }

    public function signature(): string
    {
        return 'model:'.$this->model.':'.($this->valueColumn ?? '@key').':'.$this->labelColumn
            .($this->query !== null ? ':scoped' : '');
    }

    public function isCheapToLabel(): bool
    {
        // One indexed lookup per distinct value, memoized by the field.
        return true;
    }

    private function newQuery(AjaxSelectContext $context): Builder
    {
        $query = $this->model::query()->orderBy($this->labelColumn);

        if ($this->query !== null) {
            $result = ($this->query)($query, $context);

            if ($result instanceof Builder) {
                $query = $result;
            }
        }

        return $query;
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        if ($search === null || $search === '') {
            return;
        }

        $columns = $this->searchColumns !== [] ? $this->searchColumns : [$this->labelColumn];
        $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

        $query->where(function (Builder $query) use ($columns, $term): void {
            foreach ($columns as $index => $column) {
                $index === 0
                    ? $query->where($column, 'like', $term)
                    : $query->orWhere($column, 'like', $term);
            }
        });
    }

    private function valueColumn(Model $model): string
    {
        return $this->valueColumn ?? $model->getKeyName();
    }
}
