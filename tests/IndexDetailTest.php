<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\City;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\Customer;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\Province;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Status;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;

function displayRequest(): NovaRequest
{
    $request = NovaRequest::create('/nova-api/customers', 'GET');

    app()->instance(NovaRequest::class, $request);

    return $request;
}

beforeEach(function (): void {
    AjaxSelect::flushLabelMemo();

    $province = Province::create(['name' => 'Udine']);

    City::create(['province_id' => $province->id, 'name' => 'Udine']);
    City::create(['province_id' => $province->id, 'name' => 'Tarcento']);
});

it('hides itself when it has no way to resolve a label', function (): void {
    $request = displayRequest();
    $customer = Customer::create(['city_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')->get('/api/cities/{province_id}')->parent('province_id');

    expect($field->canResolveDisplayValue())->toBeFalse()
        ->and($field->isShownOnIndex($request, $customer))->toBeFalse()
        ->and($field->isShownOnDetail($request, $customer))->toBeFalse();
});

it('renders a label read off the resource without extra queries', function (): void {
    displayRequest();

    $customer = Customer::create(['city_id' => 2]);
    $customer->load('city');

    $field = AjaxSelect::make('City', 'city_id')->optionsFromModel(City::class)->labelFrom('city.name');

    DB::enableQueryLog();
    $field->resolveForDisplay($customer);
    $log = DB::getQueryLog();

    expect($field->displayedAs)->toBe('Tarcento')
        ->and($log)->toBeEmpty()
        ->and($field->isShownOnIndex(displayRequest(), $customer))->toBeTrue();
});

it('renders an enum label for free', function (): void {
    displayRequest();

    $customer = Customer::create(['status' => 'published']);

    $field = AjaxSelect::make('Status', 'status')->optionsFromEnum(Status::class);

    DB::enableQueryLog();
    $field->resolveForDisplay($customer);

    expect($field->displayedAs)->toBe('Published')
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('resolves a label through the option source when asked', function (): void {
    displayRequest();

    $customer = Customer::create(['city_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')->optionsFromModel(City::class)->resolveLabelFromOptions();

    $field->resolveForDisplay($customer);

    expect($field->displayedAs)->toBe('Udine');
});

it('memoizes labels across the rows of an index page', function (): void {
    displayRequest();

    $customers = collect(range(1, 10))->map(fn () => Customer::create(['city_id' => 1]));

    DB::enableQueryLog();

    foreach ($customers as $customer) {
        AjaxSelect::make('City', 'city_id')
            ->optionsFromModel(City::class)
            ->resolveLabelFromOptions()
            ->resolveForDisplay($customer);
    }

    // Ten rows, one distinct city, one lookup.
    expect(DB::getQueryLog())->toHaveCount(1);
});

it('lets a display callback win', function (): void {
    displayRequest();

    $customer = Customer::create(['city_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')
        ->optionsFromModel(City::class)
        ->labelFrom('city.name')
        ->displayUsing(fn ($value) => "custom-{$value}");

    $field->resolveForDisplay($customer);

    expect($field->displayedAs)->toBe('custom-1');
});

it('falls back to nothing when the value is empty', function (): void {
    displayRequest();

    $customer = Customer::create(['city_id' => null]);

    $field = AjaxSelect::make('City', 'city_id')->optionsFromModel(City::class)->resolveLabelFromOptions();

    $field->resolveForDisplay($customer);

    expect($field->displayedAs)->toBeNull();
});
