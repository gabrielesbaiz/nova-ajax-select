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
     * Namespaced to avoid colliding with the alexwenzel/ajax-select handle.
     *
     * @var string
     */
    public $component = 'gabrielesbaiz-ajax-select';

    /**
     * Create a new field.
     *
     * @param  string  $name
     * @param  string|null  $attribute
     * @param  (callable(mixed, mixed, ?string):(mixed))|null  $resolveCallback
     */
    public function __construct($name, $attribute = null, ?callable $resolveCallback = null)
    {
        parent::__construct($name, $attribute, $resolveCallback);

        // Registered even without parents so the field always has a dependent
        // component key, and registered first so dependsOn() callbacks run
        // once the options are in place.
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
                'parentLabels' => $this->parentLabels(),
                // Seeds the first sync request, which the event bus cannot do on mount.
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
     * Display values using their corresponding specified labels.
     *
     * @return $this
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
