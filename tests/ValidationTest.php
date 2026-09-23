<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\Rules\ValueIsAnOption;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\City;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\Province;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Nova\Http\Requests\NovaRequest;

function storeRequest(array $input): NovaRequest
{
    $request = NovaRequest::create('/nova-api/customers', 'POST', $input);

    app()->instance(NovaRequest::class, $request);

    return $request;
}

function scopedCityField(): AjaxSelect
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

    City::create(['province_id' => $udine->id, 'name' => 'Udine']);
    City::create(['province_id' => $trieste->id, 'name' => 'Trieste']);
});

it('accepts a value that belongs to the submitted parent', function (): void {
    $request = storeRequest(['province_id' => 1, 'city_id' => 1]);

    $rules = scopedCityField()->getRules($request);

    expect(Validator::make($request->all(), $rules)->passes())->toBeTrue();
});

it('rejects a value that belongs to a different parent', function (): void {
    $request = storeRequest(['province_id' => 1, 'city_id' => 2]);

    $rules = scopedCityField()->getRules($request);

    expect(Validator::make($request->all(), $rules)->passes())->toBeFalse();
});

it('rejects a value that does not exist at all', function (): void {
    $request = storeRequest(['province_id' => 1, 'city_id' => 999]);

    expect(Validator::make($request->all(), scopedCityField()->getRules($request))->passes())->toBeFalse();
});

it('leaves presence checks to the other rules', function (): void {
    $request = storeRequest(['province_id' => 1, 'city_id' => null]);

    expect(Validator::make($request->all(), scopedCityField()->nullable()->getRules($request))->passes())
        ->toBeTrue();
});

it('appends to existing rules without collapsing a pipe string', function (): void {
    $request = storeRequest(['province_id' => 1, 'city_id' => 1]);

    $rules = scopedCityField()->rules('required|max:5')->getRules($request)['city_id'];

    expect($rules)->toHaveCount(3)
        ->and($rules[0])->toBe('required')
        ->and($rules[1])->toBe('max:5')
        ->and($rules[2])->toBeInstanceOf(ValueIsAnOption::class);
});

it('leaves a regex rule intact', function (): void {
    $request = storeRequest(['city_id' => 1]);

    $rules = AjaxSelect::make('City', 'city_id')
        ->optionsFromModel(City::class)
        ->rules('regex:/^\d+|\d+$/')
        ->getRules($request)['city_id'];

    expect($rules[0])->toBe('regex:/^\d+|\d+$/');
});

it('can be disabled per field', function (): void {
    $request = storeRequest(['province_id' => 1, 'city_id' => 999]);

    $rules = scopedCityField()->withoutOptionValidation()->getRules($request);

    expect(Validator::make($request->all(), $rules)->passes())->toBeTrue();
});

it('can be disabled application wide', function (): void {
    config()->set('nova-ajax-select.validation.enabled', false);

    $request = storeRequest(['province_id' => 1, 'city_id' => 999]);

    expect(Validator::make($request->all(), scopedCityField()->getRules($request))->passes())->toBeTrue();
});

it('adds no rule in endpoint mode because the options are unknowable', function (): void {
    $request = storeRequest(['province_id' => 1, 'city_id' => 999]);

    $field = AjaxSelect::make('City', 'city_id')->get('/api/cities/{province_id}')->parent('province_id');

    expect($field->getRules($request))->toBe(['city_id' => []]);
});

it('honours a custom validator even in endpoint mode', function (): void {
    $request = storeRequest(['city_id' => 999]);

    $field = AjaxSelect::make('City', 'city_id')
        ->get('/api/cities')
        ->validateOptionsUsing(fn (mixed $value) => $value === 1);

    expect(Validator::make($request->all(), $field->getRules($request))->passes())->toBeFalse();
});

it('checks membership with a single exists query', function (): void {
    $request = storeRequest(['province_id' => 1, 'city_id' => 1]);

    $rules = scopedCityField()->getRules($request);

    DB::enableQueryLog();
    Validator::make($request->all(), $rules)->passes();
    $log = DB::getQueryLog();

    expect($log)->toHaveCount(1)
        ->and($log[0]['query'])->toContain('exists');
});

it('uses a custom failure message', function (): void {
    $request = storeRequest(['province_id' => 1, 'city_id' => 2]);

    $rules = scopedCityField()->optionValidationMessage('Pick a city in that province.')->getRules($request);

    $validator = Validator::make($request->all(), $rules);

    expect($validator->errors()->first('city_id'))->toBe('Pick a city in that province.');
});
