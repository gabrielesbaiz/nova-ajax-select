<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Support;

use Closure;
use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Http\Requests\NovaRequest;

final class AjaxSelectContext
{
    /**
     * The payload key carrying the search term inside Nova's field-sync request.
     *
     * @var string
     */
    public const SEARCH_KEY = '__ajaxSelectSearch';

    /**
     * Indicates if the resource model has been resolved.
     */
    private bool $modelWasResolved = false;

    /**
     * The resolved resource model.
     */
    private ?Model $resolvedModel = null;

    /**
     * Create a new context instance.
     *
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
     * Create a new context for a dependent field sync.
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
     * Create a new context outside of a sync, such as when a form is first rendered.
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
     * Create a new context for validation, reading parents from the submitted payload.
     */
    public static function forValidation(AjaxSelect $field, NovaRequest $request): self
    {
        return new self(
            request: $request,
            formData: null,
            parents: self::parentsFrom($field, static fn (string $attribute) => $request->input($attribute)),
            value: $request->input($field->attribute),
            search: null,
            // Sources that cannot answer contains() cheaply fall back to resolving.
            limit: 0,
            mode: self::modeFrom($request),
            actionUriKey: $request->query('action'),
            modelResolver: self::modelResolver($request),
        );
    }

    /**
     * Get the given parent value, defaulting to the first declared parent.
     */
    public function parent(?string $attribute = null): mixed
    {
        if ($attribute !== null) {
            return $this->parents[$attribute] ?? null;
        }

        return array_values($this->parents)[0] ?? null;
    }

    /**
     * Get the given parent values, or all of them.
     *
     * @return array<int, mixed>
     */
    public function parents(string ...$attributes): array
    {
        if ($attributes === []) {
            return array_values($this->parents);
        }

        return array_map(fn (string $attribute): mixed => $this->parents[$attribute] ?? null, $attributes);
    }

    /**
     * Determine if every declared parent has a value.
     */
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

    /**
     * Determine if the field has a submitted or stored value.
     */
    public function hasValue(): bool
    {
        return $this->value !== null && $this->value !== '';
    }

    /**
     * Determine if the request carries a search term.
     */
    public function isSearching(): bool
    {
        return $this->search !== null && $this->search !== '';
    }

    /**
     * Determine if the request is for an action modal.
     */
    public function isAction(): bool
    {
        return $this->mode === 'action';
    }

    /**
     * Determine if the request is for a form.
     */
    public function isForm(): bool
    {
        return in_array($this->mode, ['create', 'update', 'attach', 'update-attached', 'action'], true);
    }

    /**
     * Get the resource model being edited, resolving it on first use.
     */
    public function model(): ?Model
    {
        if (! $this->modelWasResolved) {
            $this->modelWasResolved = true;
            $this->resolvedModel = $this->modelResolver === null ? null : ($this->modelResolver)();
        }

        return $this->resolvedModel;
    }

    /**
     * Get the authenticated user.
     */
    public function user(): ?Authenticatable
    {
        return $this->request->user();
    }

    /**
     * Read the field's parent values using the given reader.
     *
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

    /**
     * Get the search term carried by the given request.
     */
    private static function searchFrom(NovaRequest $request): ?string
    {
        $search = $request->input(self::SEARCH_KEY);

        if (! is_string($search)) {
            return null;
        }

        $search = trim($search);

        return $search === '' ? null : $search;
    }

    /**
     * Determine the mode the given request is made in.
     */
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
     * Get the callback that resolves the resource model for the given request.
     *
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
