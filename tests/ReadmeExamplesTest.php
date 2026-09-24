<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\City;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\Region;
use Laravel\Nova\Http\Requests\NovaRequest;

function readmeRequest(array $input = []): NovaRequest
{
    $request = NovaRequest::create('/nova-api/customers/creation-fields', 'PATCH', array_merge($input, [
        'editing' => 'true',
        'editMode' => 'create',
    ]));

    app()->instance(NovaRequest::class, $request);

    return $request;
}

beforeEach(function (): void {
    $ontario = Region::create(['name' => 'Ontario', 'country' => 'ca']);
    Region::create(['name' => 'Kantō', 'country' => 'jp']);

    City::create(['region_id' => $ontario->id, 'name' => 'Toronto']);
    City::create(['region_id' => 2, 'name' => 'Tokyo']);
});

it('runs the headline example', function (): void {
    readmeRequest(['country' => 'ca']);

    $region = AjaxSelect::make('Region', 'region_id')
        ->parent('country')
        ->optionsFromModel(Region::class, query: fn ($query, $context) => $query->where('country', $context->parent()));

    expect(collect($region->jsonSerialize()['options'])->pluck('label')->all())->toBe(['Ontario']);

    readmeRequest(['region_id' => 1]);

    $city = AjaxSelect::make('City', 'city_id')
        ->parent('region_id')
        ->optionsFromModel(City::class, query: fn ($query, $context) => $query->where('region_id', $context->parent()))
        ->labelFrom('city.name');

    expect(collect($city->jsonSerialize()['options'])->pluck('label')->all())->toBe(['Toronto']);
});
