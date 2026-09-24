<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\NovaAjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\City;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\Region;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Status;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Create the request Nova makes when rendering or syncing a form.
 */
function novaRequest(array $input = [], string $editMode = 'create', string $uri = '/nova-api/customers'): NovaRequest
{
    $request = NovaRequest::create($uri, 'POST', array_merge($input, [
        'editing' => 'true',
        'editMode' => $editMode,
    ]));

    $request->setRouteResolver(fn () => null);

    app()->instance(NovaRequest::class, $request);

    return $request;
}

beforeEach(function (): void {
    AjaxSelect::flushLabelMemo();

    $region = Region::create(['name' => 'Udine']);

    City::create(['region_id' => $region->id, 'name' => 'Udine', 'zip_code' => '33100']);
    City::create(['region_id' => $region->id, 'name' => 'Tarcento', 'zip_code' => '33017']);
    City::create(['region_id' => 999, 'name' => 'Trieste', 'zip_code' => '34100']);
});

it('serializes the ajax select meta block', function (): void {
    novaRequest(['region_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')->parent('region_id');

    $meta = $field->jsonSerialize()['ajaxSelect'];

    expect($meta['parents'])->toBe(['region_id'])
        ->and($meta['parentValues'])->toBe(['region_id' => 1])
        ->and($meta['mode'])->toBe('options')
        ->and($meta['searchKey'])->toBe(AjaxSelectContext::SEARCH_KEY)
        ->and($meta['clearOnParentChange'])->toBeTrue();
});

it('uses a namespaced component so it cannot collide with the upstream package', function (): void {
    expect(AjaxSelect::make('City')->component)->toBe('gabrielesbaiz-ajax-select');
});

it('resolves options scoped to the parent value', function (): void {
    novaRequest(['region_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')
        ->parent('region_id')
        ->optionsFromModel(City::class, query: fn (Builder $q, $c) => $q->where('region_id', $c->parent()));

    expect(collect($field->jsonSerialize()['options'])->pluck('label')->all())
        ->toBe(['Tarcento', 'Udine']);
});

it('resolves no options while the parent is empty', function (): void {
    novaRequest([]);

    $field = AjaxSelect::make('City', 'city_id')
        ->parent('region_id')
        ->optionsFromModel(City::class, query: fn (Builder $q, $c) => $q->where('region_id', $c->parent()));

    expect($field->jsonSerialize()['options'])->toBe([]);
});

it('resolves options anyway when told the parent is optional', function (): void {
    novaRequest([]);

    $field = AjaxSelect::make('City', 'city_id')
        ->parent('region_id')
        ->emptyWhenParentMissing(false)
        ->optionsFromModel(City::class);

    expect($field->jsonSerialize()['options'])->toHaveCount(3);
});

it('passes the context to an options closure', function (): void {
    novaRequest(['region_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')
        ->parent('region_id')
        ->options(fn (AjaxSelectContext $context) => City::query()
            ->where('region_id', $context->parent())
            ->pluck('name', 'id'));

    expect(collect($field->jsonSerialize()['options'])->pluck('label')->all())
        ->toBe(['Udine', 'Tarcento']);
});

it('accepts closures of any arity', function (): void {
    novaRequest([]);

    expect(AjaxSelect::make('A')->options(fn () => ['x' => 'X'])->jsonSerialize()['options'])
        ->toBe([['value' => 'x', 'label' => 'X']])
        ->and(AjaxSelect::make('B')->options(fn ($context) => ['y' => 'Y'])->jsonSerialize()['options'])
        ->toBe([['value' => 'y', 'label' => 'Y']])
        ->and(AjaxSelect::make('C')->options(fn ($context, $request) => ['z' => 'Z'])->jsonSerialize()['options'])
        ->toBe([['value' => 'z', 'label' => 'Z']]);
});

it('resolves options from an enum', function (): void {
    novaRequest([]);

    $options = AjaxSelect::make('Status')->optionsFromEnum(Status::class)->jsonSerialize()['options'];

    expect(collect($options)->pluck('value')->all())->toBe(['draft', 'published', 'archived']);
});

it('keeps the legacy endpoint mode working', function (): void {
    novaRequest(['region_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')
        ->get('/api/cities/{region_id}')
        ->parent('region_id');

    $meta = $field->jsonSerialize()['ajaxSelect'];

    expect($meta['mode'])->toBe('endpoint')
        ->and($meta['endpoint'])->toBe('/api/cities/{region_id}')
        ->and($field->usesEndpoint())->toBeTrue();
});

it('still resolves the deprecated class name', function (): void {
    expect(NovaAjaxSelect::make('City'))->toBeInstanceOf(AjaxSelect::class);
});

it('caps the serialized option set', function (): void {
    novaRequest([]);

    $field = AjaxSelect::make('City', 'city_id')->optionsFromModel(City::class)->limit(2);

    expect($field->jsonSerialize()['options'])->toHaveCount(2);
});

it('accepts a custom label and value column', function (): void {
    novaRequest([]);

    $field = AjaxSelect::make('City', 'city_id')
        ->optionsFromModel(City::class, label: 'name', value: 'zip_code');

    // Ordered by the label column: Tarcento, Trieste, Udine.
    expect(collect($field->jsonSerialize()['options'])->pluck('value')->all())->toBe([33017, 34100, 33100]);
});
