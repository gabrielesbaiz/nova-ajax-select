<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Support;

use Closure;
use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Everything an options resolver is allowed to know about the current request.
 *
 * Parent values always come from Nova's FormData, which is already restricted
 * to the attributes the field declared - a resolver never reads raw input.
 */
final class AjaxSelectContext
{
    /**
     * The payload key the Vue component uses to smuggle a search term into
     * Nova's own field-sync request, so this package owns no HTTP route.
     */
    public const SEARCH_KEY = '__ajaxSelectSearch';

    private bool $modelWasResolved = false;

    private ?Model $resolvedModel = null;

    /**
     * @param  array<string, mixed>  $parents
     * @param  (Closure(): (Model|null))|null  $modelResolver
     */
    public function __construct(
        public readonly NovaRequest $request,
        public readonly ?FormData $formData,
        public readonly array $parents,
        public readonly mixed $value = null,
        public readonly ?string $search = null,
        public readonly int $limit = 50,
        public readonly string $mode = 'create',
        public readonly ?string $actionUriKey = null,
        private readonly ?Closure $modelResolver = null,
    ) {}

    /**
     * Build the context for a dependent-field sync (the common path).
     */
    public static function forSync(AjaxSelect $field, NovaRequest $request, FormData $formData): self
    {
        return new self(
            request: $request,
            formData: $formData,
            parents: self::parentsFrom($field, static fn (string $attribute) => $formData->get($attribute)),
            value: $formData->get($field->attribute),
            search: self::searchFrom($request),
            limit: $field->optionsLimit(),
            mode: self::modeFrom($request),
            actionUriKey: $request->query('action'),
            modelResolver: self::modelResolver($request),
        );
    }

    /**
     * Build the context outside a sync, e.g. when the form is first rendered.
     */
    public static function forRequest(AjaxSelect $field, NovaRequest $request): self
    {
        return new self(
            request: $request,
            formData: null,
            parents: self::parentsFrom($field, static fn (string $attribute) => $request->input($attribute)),
            value: $field->value ?? $request->input($field->attribute),
            search: self::searchFrom($request),
            limit: $field->optionsLimit(),
            mode: self::modeFrom($request),
            actionUriKey: $request->query('action'),
            modelResolver: self::modelResolver($request),
        );
    }

    /**
     * Build the context for validation, reading parents from the submitted payload
     * so a tampered child value is checked against the submitted parent.
     */
    public static function forValidation(AjaxSelect $field, NovaRequest $request): self
    {
        return new self(
            request: $request,
            formData: null,
            parents: self::parentsFrom($field, static fn (string $attribute) => $request->input($attribute)),
            value: $request->input($field->attribute),
            search: null,
            // Validation never materializes the full option set, but a source
            // that cannot answer contains() cheaply falls back to resolving.
            limit: 0,
            mode: self::modeFrom($request),
            actionUriKey: $request->query('action'),
            modelResolver: self::modelResolver($request),
        );
    }

    /**
     * Get a parent value; defaults to the first declared parent.
     */
    public function parent(?string $attribute = null): mixed
    {
        if ($attribute !== null) {
            return $this->parents[$attribute] ?? null;
        }

        return array_values($this->parents)[0] ?? null;
    }

    /**
     * @return array<int, mixed>
     */
    public function parents(string ...$attributes): array
    {
        if ($attributes === []) {
            return array_values($this->parents);
        }

        return array_map(fn (string $attribute): mixed => $this->parents[$attribute] ?? null, $attributes);
    }

    public function hasAllParents(): bool
    {
        if ($this->parents === []) {
            return true;
        }

        foreach ($this->parents as $value) {
            if ($value === null || $value === '' || $value === []) {
                return false;
            }
        }

        return true;
    }

    public function hasValue(): bool
    {
        return $this->value !== null && $this->value !== '';
    }

    public function isSearching(): bool
    {
        return $this->search !== null && $this->search !== '';
    }

    public function isAction(): bool
    {
        return $this->mode === 'action';
    }

    public function isForm(): bool
    {
        return in_array($this->mode, ['create', 'update', 'attach', 'update-attached', 'action'], true);
    }

    /**
     * The resource model being edited, or null on create and in action modals.
     *
     * Deliberately a method: resolving it costs a query that most resolvers
     * never need.
     */
    public function model(): ?Model
    {
        if (! $this->modelWasResolved) {
            $this->modelWasResolved = true;
            $this->resolvedModel = $this->modelResolver === null ? null : ($this->modelResolver)();
        }

        return $this->resolvedModel;
    }

    public function user(): ?Authenticatable
    {
        return $this->request->user();
    }

    /**
     * @param  Closure(string): mixed  $reader
     * @return array<string, mixed>
     */
    private static function parentsFrom(AjaxSelect $field, Closure $reader): array
    {
        $parents = [];

        foreach ($field->parentAttributes() as $attribute) {
            $value = $reader($attribute);

            $parents[$attribute] = $value === '' ? null : $value;
        }

        return $parents;
    }

    private static function searchFrom(NovaRequest $request): ?string
    {
        $search = $request->input(self::SEARCH_KEY);

        if (! is_string($search)) {
            return null;
        }

        $search = trim($search);

        return $search === '' ? null : $search;
    }

    private static function modeFrom(NovaRequest $request): string
    {
        return match (true) {
            $request->isActionRequest() => 'action',
            $request->isUpdateOrUpdateAttachedRequest() => $request->relatedResource ? 'update-attached' : 'update',
            $request->isCreateOrAttachRequest() => $request->relatedResource ? 'attach' : 'create',
            $request->isResourceIndexRequest() => 'index',
            $request->isResourceDetailRequest() => 'detail',
            default => 'other',
        };
    }

    /**
     * @return (Closure(): (Model|null))|null
     */
    private static function modelResolver(NovaRequest $request): ?Closure
    {
        if (blank($request->resource) || blank($request->resourceId)) {
            return null;
        }

        return static function () use ($request): ?Model {
            try {
                return $request->findModelQuery()->firstOrFail();
            } catch (\Throwable) {
                return null;
            }
        };
    }
}
