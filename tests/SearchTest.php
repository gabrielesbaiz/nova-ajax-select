<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\City;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\Province;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;

function searchRequest(?string $search = null, array $input = []): NovaRequest
{
    $request = NovaRequest::create('/nova-api/customers/creation-fields', 'PATCH', array_merge($input, [
        'editing' => 'true',
        'editMode' => 'create',
    ] + ($search === null ? [] : [AjaxSelectContext::SEARCH_KEY => $search])));

    app()->instance(NovaRequest::class, $request);

    return $request;
}

beforeEach(function (): void {
    AjaxSelect::flushLabelMemo();

    $province = Province::create(['name' => 'Udine']);

    foreach (['Udine', 'Tarcento', 'Codroipo', 'Cividale'] as $name) {
        City::create(['province_id' => $province->id, 'name' => $name]);
    }
});

it('waits for a search term before resolving a searchable field', function (): void {
    searchRequest();

    $field = AjaxSelect::make('City', 'city_id')
        ->optionsFromModel(City::class)
        ->asyncSearchable()
        ->minSearchLength(2);

    expect($field->jsonSerialize()['options'])->toBe([]);
});

it('still shows the stored value while waiting for a search term', function (): void {
    searchRequest(null, ['city_id' => 2]);

    $field = AjaxSelect::make('City', 'city_id')
        ->optionsFromModel(City::class)
        ->asyncSearchable()
        ->minSearchLength(2);

    expect($field->jsonSerialize()['options'])->toBe([['value' => 2, 'label' => 'Tarcento']]);
});

it('ignores a search term shorter than the minimum', function (): void {
    searchRequest('C');

    $field = AjaxSelect::make('City', 'city_id')
        ->optionsFromModel(City::class)
        ->asyncSearchable()
        ->minSearchLength(2);

    expect($field->jsonSerialize()['options'])->toBe([]);
});

it('pushes the search into sql for a model source', function (): void {
    searchRequest('Cod');

    $field = AjaxSelect::make('City', 'city_id')->optionsFromModel(City::class)->asyncSearchable();

    DB::enableQueryLog();
    $options = $field->jsonSerialize()['options'];
    $log = DB::getQueryLog();

    expect(collect($options)->pluck('label')->all())->toBe(['Codroipo'])
        ->and($log)->toHaveCount(1)
        ->and($log[0]['query'])->toContain('like');
});

it('searches across the configured columns', function (): void {
    searchRequest('33100');

    City::query()->whereKey(1)->update(['zip_code' => '33100']);

    $field = AjaxSelect::make('City', 'city_id')
        ->searchColumns('name', 'zip_code')
        ->optionsFromModel(City::class)
        ->asyncSearchable();

    expect(collect($field->jsonSerialize()['options'])->pluck('label')->all())->toBe(['Udine']);
});

it('filters a callback source in php', function (): void {
    searchRequest('Civ');

    $field = AjaxSelect::make('City', 'city_id')
        ->options(fn () => City::query()->pluck('name', 'id'))
        ->asyncSearchable();

    expect(collect($field->jsonSerialize()['options'])->pluck('label')->all())->toBe(['Cividale']);
});

it('prefers a dedicated search callback', function (): void {
    searchRequest('anything');

    $field = AjaxSelect::make('City', 'city_id')
        ->options(fn () => ['unsearched' => 'Unsearched'])
        ->searchUsing(fn (string $search) => ['searched' => $search]);

    expect($field->jsonSerialize()['options'])->toBe([['value' => 'searched', 'label' => 'anything']]);
});

it('keeps the selected option visible in a filtered result set', function (): void {
    searchRequest('Cod', ['city_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')->optionsFromModel(City::class)->asyncSearchable();

    expect(collect($field->jsonSerialize()['options'])->pluck('label')->all())
        ->toBe(['Udine', 'Codroipo']);
});

it('limits search results', function (): void {
    searchRequest('C');

    $field = AjaxSelect::make('City', 'city_id')->optionsFromModel(City::class)->asyncSearchable()->limit(1);

    expect($field->jsonSerialize()['options'])->toHaveCount(1);
});

it('reports itself as searchable to nova', function (): void {
    $request = searchRequest();

    expect(AjaxSelect::make('City')->asyncSearchable()->isSearchable($request))->toBeTrue()
        ->and(AjaxSelect::make('City')->isSearchable($request))->toBeFalse();
});
