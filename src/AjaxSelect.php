<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect;

use Gabrielesbaiz\NovaAjaxSelect\Concerns\CachesOptions;
use Gabrielesbaiz\NovaAjaxSelect\Concerns\HasParentAttributes;
use Gabrielesbaiz\NovaAjaxSelect\Concerns\ResolvesDisplayValues;
use Gabrielesbaiz\NovaAjaxSelect\Concerns\ResolvesOptions;
use Gabrielesbaiz\NovaAjaxSelect\Concerns\SearchesOptions;
use Gabrielesbaiz\NovaAjaxSelect\Concerns\SupportsLegacyEndpoint;
use Gabrielesbaiz\NovaAjaxSelect\Concerns\ValidatesAgainstOptions;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Support\ResolveAjaxSelectOptions;
use Laravel\Nova\Fields\Dependent;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * A select field whose options are resolved on the server whenever the fields
 * it depends on change.
 *
 * `parent()` is sugar over Nova's own dependent-field machinery, so the field
 * composes with `dependsOn()`, `hide()`, `show()` and `readonly()`, and works
 * unchanged inside panels, pivot forms and action modals.
 */
class AjaxSelect extends Select
{
    use CachesOptions;
    use HasParentAttributes;
    use ResolvesDisplayValues;
    use ResolvesOptions;
    use SearchesOptions;
    use SupportsLegacyEndpoint;
    use ValidatesAgainstOptions;

    /**
     * The field's component.
     *
     * Namespaced on purpose: the 1.x handle collided with the upstream
     * alexwenzel/ajax-select package, which silently won and left this field
     * rendering as an unknown component.
     *
     * @var string
     */
    public $component = 'gabrielesbaiz-ajax-select';

    /**
     * @param  string  $name
     * @param  string|null  $attribute
     */
    public function __construct($name, $attribute = null, ?callable $resolveCallback = null)
    {
        parent::__construct($name, $attribute, $resolveCallback);

        // One owned Dependent, registered even with no parents, so the field is
        // always given a dependentComponentKey and Nova will re-resolve it on
        // sync. Pushed first so user-registered dependsOn() callbacks run after
        // the options are in place and can still hide() or readonly() the field.
        $this->ajaxDependent = new Dependent([], new ResolveAjaxSelectOptions);
        $this->fieldDependencies[] = $this->ajaxDependent;
    }

    /**
     * Prepare the field for JSON serialization.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        $context = $this->currentContext();

        return array_merge(parent::jsonSerialize(), [
            'ajaxSelect' => [
                'parents' => $this->parentAttributes,
                // The event bus emits nothing on mount, so an edit form cannot
                // seed its first request without the parent values.
                'parentValues' => $context->parents,
                'selectedOption' => $this->selectedOption($context)?->toArray(),
                'mode' => $this->usesEndpoint() ? 'endpoint' : 'options',
                'endpoint' => $this->endpointUrl(),
                'searchKey' => AjaxSelectContext::SEARCH_KEY,
                'asyncSearchable' => $this->isAsyncSearchable(),
                'minSearchLength' => $this->minimumSearchLength(),
                'limit' => $this->optionsLimit(),
                'clearOnParentChange' => $this->clearsWhenParentChanges,
            ],
        ]);
    }

    /**
     * Display values using their corresponding labels.
     *
     * Overridden so the label comes from the option source rather than from
     * Nova's static options array, which an ajax field never fills.
     */
    #[\Override]
    public function displayUsingLabels()
    {
        return $this->resolveLabelFromOptions();
    }

    /**
     * Determine if the field is searchable for the given request.
     */
    #[\Override]
    public function isSearchable(NovaRequest $request): bool
    {
        return $this->isAsyncSearchable() || parent::isSearchable($request);
    }
}
