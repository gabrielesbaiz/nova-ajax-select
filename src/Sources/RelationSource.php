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
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * A relation on the resource model currently being edited.
 *
 * There is no model on create or inside an action modal, so the source is
 * empty there unless a query closure builds the relation another way.
 */
final class RelationSource implements OptionSource
{
    /**
     * @param  (Closure(Builder, AjaxSelectContext): mixed)|null  $query
     * @param  array<int, string>  $searchColumns
     */
    public function __construct(
        private readonly string $relation,
        private readonly string $labelColumn = 'name',
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

        if ($query === null) {
            return OptionCollection::empty();
        }

        $this->applySearch($query, $context->search);

        if ($context->limit > 0) {
            $query->limit($context->limit);
        }

        return OptionCollection::make(
            $query->get()->map(fn (Model $model): Option => new Option(
                value: Option::castValue($model->getKey()),
                label: (string) $model->getAttribute($this->labelColumn),
            ))->all()
        );
    }

    public function contains(mixed $value, AjaxSelectContext $context): bool
    {
        $query = $this->newQuery($context);

        if ($query === null || $value === null || $value === '') {
            return false;
        }

        return $query->whereKey($value)->exists();
    }

    public function label(mixed $value, AjaxSelectContext $context): ?string
    {
        $query = $this->newQuery($context);

        if ($query === null || $value === null || $value === '') {
            return null;
        }

        $label = $query->whereKey($value)->value($this->labelColumn);

        return $label === null ? null : (string) $label;
    }

    public function signature(): string
    {
        return 'relation:'.$this->relation.':'.$this->labelColumn.($this->query !== null ? ':scoped' : '');
    }

    public function isCheapToLabel(): bool
    {
        return true;
    }

    private function newQuery(AjaxSelectContext $context): ?Builder
    {
        $model = $context->model();

        if ($model === null || ! method_exists($model, $this->relation)) {
            return null;
        }

        $relation = $model->{$this->relation}();

        if (! $relation instanceof Relation) {
            return null;
        }

        $query = $relation->getQuery()->orderBy($this->labelColumn);

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
}
