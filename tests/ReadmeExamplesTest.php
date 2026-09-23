<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\City;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\Province;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Status;
use Illuminate\Database\Eloquent\Builder;
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
    $udine = Province::create(['name' => 'Udine']);

    City::create(['province_id' => $udine->id, 'name' => 'Udine', 'zip_code' => '33100']);
    City::create(['province_id' => 2, 'name' => 'Trieste', 'zip_code' => '34100']);
});

it('runs the headline example', function (): void {
    readmeRequest(['province_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')
        ->parent('province_id')
        ->optionsFromModel(City::class, query: fn ($query, $context) => $query->where('province_id', $context->parent()))
        ->labelFrom('city.name');

    expect(collect($field->jsonSerialize()['options'])->pluck('label')->all())->toBe(['Udine']);
});

it('runs the closure example', function (): void {
    readmeRequest(['province_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')
        ->parent('province_id')
        ->options(fn ($context) => City::where('province_id', $context->parent())->pluck('name', 'id'));

    expect($field->jsonSerialize()['options'])->toBe([['value' => 1, 'label' => 'Udine']]);
});

it('runs the multiple parents example', function (): void {
    readmeRequest(['brand_id' => 1, 'year' => 2020]);

    $seen = [];

    $field = AjaxSelect::make('Model', 'model_id')
        ->parent('brand_id', 'year')
        ->options(function (AjaxSelectContext $context) use (&$seen) {
            $seen = [$context->parent('brand_id'), $context->parent('year')];

            return ['a' => 'A'];
        });

    $field->jsonSerialize();

    expect($seen)->toBe([1, 2020]);
});

it('resolves nothing until every parent is filled in', function (): void {
    readmeRequest(['brand_id' => 1]);

    $field = AjaxSelect::make('Model', 'model_id')
        ->parent('brand_id', 'year')
        ->options(fn () => ['a' => 'A']);

    expect($field->jsonSerialize()['options'])->toBe([]);
});

it('accepts the documented named arguments', function (): void {
    readmeRequest();

    $field = AjaxSelect::make('City', 'city_id')
        ->optionsFromModel(City::class, label: 'name', value: 'zip_code');

    // Ordered by the label column, so Trieste precedes Udine.
    expect(collect($field->jsonSerialize()['options'])->pluck('value')->all())->toBe([34100, 33100]);
});

it('accepts a DateTimeInterface ttl and a named store', function (): void {
    readmeRequest();

    $field = AjaxSelect::make('City', 'city_id')
        ->optionsFromModel(City::class)
        ->cacheFor(now()->addDay(), store: 'array');

    expect($field->jsonSerialize()['options'])->toHaveCount(2);
});

it('treats displayUsingLabels as the option source strategy', function (): void {
    readmeRequest();

    $field = AjaxSelect::make('Status', 'status')->optionsFromEnum(Status::class)->displayUsingLabels();

    expect($field->canResolveDisplayValue())->toBeTrue();
});

it('runs the documented search example', function (): void {
    $request = NovaRequest::create('/nova-api/customers/creation-fields', 'PATCH', [
        'editing' => 'true',
        'editMode' => 'create',
        AjaxSelectContext::SEARCH_KEY => '34100',
    ]);
    app()->instance(NovaRequest::class, $request);

    $field = AjaxSelect::make('City', 'city_id')
        ->optionsFromModel(City::class)
        ->searchColumns('name', 'zip_code')
        ->asyncSearchable()
        ->minSearchLength(2)
        ->limit(25)
        ->debounce(400);

    expect(collect($field->jsonSerialize()['options'])->pluck('label')->all())->toBe(['Trieste']);
});

it('runs the documented composition example', function (): void {
    $request = readmeRequest(['province_id' => 1, 'has_address' => true]);

    $field = AjaxSelect::make('City', 'city_id')
        ->parent('province_id')
        ->optionsFromModel(City::class, query: fn (Builder $q, $c) => $q->where('province_id', $c->parent()))
        ->hide()
        ->dependsOn('has_address', function (AjaxSelect $field, NovaRequest $request, $formData): void {
            if ($formData->boolean('has_address')) {
                $field->show()->rules('required');
            }
        })
        ->readonly(fn () => false);

    $field->syncDependsOn($request);

    expect($field->jsonSerialize()['visible'])->toBeTrue()
        ->and(collect($field->jsonSerialize()['options'])->pluck('label')->all())->toBe(['Udine']);
});

it('accepts every documented payload shape', function (): void {
    readmeRequest();

    $shapes = [
        ['it' => 'Italy'],
        [['value' => 1, 'label' => 'Udine']],
        [['value' => 1, 'display' => 'Udine']],
        [['id' => 1, 'name' => 'Udine']],
        ['options' => [['value' => 1, 'label' => 'Udine']]],
        ['data' => [['value' => 1, 'label' => 'Udine']]],
    ];

    foreach ($shapes as $shape) {
        expect(AjaxSelect::make('X', 'x')->options($shape)->jsonSerialize()['options'])
            ->not->toBeEmpty();
    }

    expect(AjaxSelect::make('X', 'x')->options(City::all())->jsonSerialize()['options'])->toHaveCount(2)
        ->and(AjaxSelect::make('X', 'x')->options(Status::cases())->jsonSerialize()['options'])->toHaveCount(3);
});
