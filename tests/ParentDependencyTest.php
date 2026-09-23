<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\City;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\Province;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;

function syncRequest(array $input = [], string $editMode = 'create'): NovaRequest
{
    $request = NovaRequest::create('/nova-api/customers/creation-fields', 'PATCH', array_merge($input, [
        'editing' => 'true',
        'editMode' => $editMode,
    ]));

    app()->instance(NovaRequest::class, $request);

    return $request;
}

function cityField(): AjaxSelect
{
    return AjaxSelect::make('City', 'city_id')
        ->parent('province_id')
        ->optionsFromModel(
            City::class,
            query: fn (Builder $query, AjaxSelectContext $context) => $query->where('province_id', $context->parent())
        );
}

beforeEach(function (): void {
    AjaxSelect::flushLabelMemo();

    $udine = Province::create(['name' => 'Udine']);
    $trieste = Province::create(['name' => 'Trieste']);

    City::create(['province_id' => $udine->id, 'name' => 'Udine', 'zip_code' => '33100']);
    City::create(['province_id' => $udine->id, 'name' => 'Tarcento', 'zip_code' => '33017']);
    City::create(['province_id' => $trieste->id, 'name' => 'Trieste', 'zip_code' => '34100']);
});

it('registers exactly one dependency no matter how often parent is called', function (): void {
    $field = AjaxSelect::make('City', 'city_id')
        ->parent('province_id')
        ->parent('province_id')
        ->parent('country_id');

    $request = syncRequest();

    expect($field->parentAttributes())->toBe(['province_id', 'country_id'])
        ->and($field->applyDependsOn($request)->jsonSerialize()['dependsOn'])
        ->toHaveKeys(['province_id', 'country_id']);

    // One Dependent means one sync pass, so the options resolve once.
    $dependencies = (new ReflectionProperty($field, 'fieldDependencies'))->getValue($field);

    expect($dependencies)->toHaveCount(1);
});

it('keeps user registered dependsOn callbacks and runs them after ours', function (): void {
    $order = [];

    $field = cityField()->dependsOn('province_id', function (AjaxSelect $field) use (&$order): void {
        $order[] = 'user';
        $field->readonly();
    });

    $field->applyDependsOn(syncRequest(['province_id' => 1]));

    expect($order)->toBe(['user'])
        ->and($field->readonlyCallback)->toBeTruthy()
        // Ours ran first, so the user callback saw the resolved options.
        ->and($field->jsonSerialize()['options'])->toHaveCount(2);
});

it('resolves options for the submitted parent during a sync', function (): void {
    $field = cityField();

    $field->syncDependsOn(syncRequest(['province_id' => 2]));

    expect(collect($field->jsonSerialize()['options'])->pluck('label')->all())->toBe(['Trieste']);
});

it('clears a value that no longer belongs to the new parent', function (): void {
    $field = cityField();

    // City 1 belongs to province 1, so selecting province 2 invalidates it.
    $field->syncDependsOn(syncRequest(['province_id' => 2, 'city_id' => 1]));

    // Empty string, not null: a null synced value makes Nova keep the old one.
    expect($field->value)->toBe('');
});

it('keeps a value that is still valid for the new parent', function (): void {
    $field = cityField();

    $field->syncDependsOn(syncRequest(['province_id' => 1, 'city_id' => 2]));

    expect($field->value)->toBe(2);
});

it('can be told not to clear the value', function (): void {
    $field = cityField()->clearWhenParentChanges(false);

    $field->syncDependsOn(syncRequest(['province_id' => 2, 'city_id' => 1]));

    expect($field->value)->toBe(1);
});

it('resolves the third level of a chain from the second', function (): void {
    $zip = AjaxSelect::make('Zip code', 'zip_code')
        ->parent('city_id')
        ->options(fn (AjaxSelectContext $context) => City::query()
            ->whereKey($context->parent())
            ->pluck('zip_code', 'zip_code'));

    $zip->syncDependsOn(syncRequest(['province_id' => 1, 'city_id' => 2]));

    // Numeric strings are cast exactly as Nova's own Select casts them.
    expect(collect($zip->jsonSerialize()['options'])->pluck('value')->all())->toBe([33017]);
});

it('reads parent values through form data rather than raw input', function (): void {
    $seen = null;

    $field = AjaxSelect::make('City', 'city_id')
        ->parent('province_id')
        ->options(function (AjaxSelectContext $context) use (&$seen) {
            $seen = $context->parents;

            return [];
        });

    $field->syncDependsOn(syncRequest(['province_id' => 2, 'unrelated' => 'nope']));
    $field->jsonSerialize();

    expect($seen)->toBe(['province_id' => 2]);
});

it('runs the resolver inside an action modal', function (): void {
    $request = NovaRequest::create('/nova-api/customers/actions', 'PATCH', ['province_id' => 2]);
    app()->instance(NovaRequest::class, $request);

    $field = cityField();
    $field->syncDependsOn($request);

    expect($request->isActionRequest())->toBeTrue()
        ->and(collect($field->jsonSerialize()['options'])->pluck('label')->all())->toBe(['Trieste']);
});

it('exposes a dependent component key so nova can find the field on sync', function (): void {
    expect(cityField()->dependentComponentKey())
        ->toBe('ajaxselect.gabrielesbaiz-ajax-select.city_id');
});
