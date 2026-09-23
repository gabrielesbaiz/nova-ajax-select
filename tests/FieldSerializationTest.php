<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\NovaAjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\City;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\Province;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Status;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Build the kind of request Nova actually makes when rendering or syncing a
 * form: `editing=true` plus an edit mode is what flips isCreateOrAttachRequest().
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

    $province = Province::create(['name' => 'Udine']);

    City::create(['province_id' => $province->id, 'name' => 'Udine', 'zip_code' => '33100']);
    City::create(['province_id' => $province->id, 'name' => 'Tarcento', 'zip_code' => '33017']);
    City::create(['province_id' => 999, 'name' => 'Trieste', 'zip_code' => '34100']);
});

it('serializes the ajax select meta block', function (): void {
    novaRequest(['province_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')->parent('province_id');

    $meta = $field->jsonSerialize()['ajaxSelect'];

    expect($meta['parents'])->toBe(['province_id'])
        ->and($meta['parentValues'])->toBe(['province_id' => 1])
        ->and($meta['mode'])->toBe('options')
        ->and($meta['searchKey'])->toBe(AjaxSelectContext::SEARCH_KEY)
        ->and($meta['clearOnParentChange'])->toBeTrue();
});

it('uses a namespaced component so it cannot collide with the upstream package', function (): void {
    expect(AjaxSelect::make('City')->component)->toBe('gabrielesbaiz-ajax-select');
});

it('resolves options scoped to the parent value', function (): void {
    novaRequest(['province_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')
        ->parent('province_id')
        ->optionsFromModel(City::class, query: fn (Builder $q, $c) => $q->where('province_id', $c->parent()));

    expect(collect($field->jsonSerialize()['options'])->pluck('label')->all())
        ->toBe(['Tarcento', 'Udine']);
});

it('resolves no options while the parent is empty', function (): void {
    novaRequest([]);

    $field = AjaxSelect::make('City', 'city_id')
        ->parent('province_id')
        ->optionsFromModel(City::class, query: fn (Builder $q, $c) => $q->where('province_id', $c->parent()));

    expect($field->jsonSerialize()['options'])->toBe([]);
});

it('resolves options anyway when told the parent is optional', function (): void {
    novaRequest([]);

    $field = AjaxSelect::make('City', 'city_id')
        ->parent('province_id')
        ->emptyWhenParentMissing(false)
        ->optionsFromModel(City::class);

    expect($field->jsonSerialize()['options'])->toHaveCount(3);
});

it('passes the context to an options closure', function (): void {
    novaRequest(['province_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')
        ->parent('province_id')
        ->options(fn (AjaxSelectContext $context) => City::query()
            ->where('province_id', $context->parent())
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
    novaRequest(['province_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')
        ->get('/api/cities/{province_id}')
        ->parent('province_id');

    $meta = $field->jsonSerialize()['ajaxSelect'];

    expect($meta['mode'])->toBe('endpoint')
        ->and($meta['endpoint'])->toBe('/api/cities/{province_id}')
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
