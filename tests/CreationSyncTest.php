<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\City;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\Customer;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\Province;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * The component key Nova uses to find the field during a sync.
 */
function componentKey(string $attribute): string
{
    return "ajaxselect.gabrielesbaiz-ajax-select.{$attribute}";
}

function syncCreationField(string $attribute, array $payload): TestResponse
{
    return test()->patchJson(
        '/nova-api/customers/creation-fields?'.http_build_query([
            'editing' => 'true',
            'editMode' => 'create',
            'field' => $attribute,
            'component' => componentKey($attribute),
        ]),
        $payload
    );
}

beforeEach(function (): void {
    test()->actingAs(User::create(['name' => 'Tester', 'email' => 'test@example.com']));

    $udine = Province::create(['name' => 'Udine']);
    $trieste = Province::create(['name' => 'Trieste']);

    City::create(['province_id' => $udine->id, 'name' => 'Udine', 'zip_code' => '33100']);
    City::create(['province_id' => $udine->id, 'name' => 'Tarcento', 'zip_code' => '33017']);
    City::create(['province_id' => $trieste->id, 'name' => 'Trieste', 'zip_code' => '34100']);
});

it('returns options scoped to the submitted parent', function (): void {
    $response = syncCreationField('city_id', ['province_id' => 2]);

    $response->assertOk();

    expect(collect($response->json('options'))->pluck('label')->all())->toBe(['Trieste']);
});

it('returns no options while the parent is empty', function (): void {
    $response = syncCreationField('city_id', ['province_id' => null]);

    $response->assertOk()->assertJsonPath('options', []);
});

it('clears a value that no longer belongs to the parent', function (): void {
    $response = syncCreationField('city_id', ['province_id' => 2, 'city_id' => 1]);

    // Empty string rather than null, so the browser clears the input and
    // cascades the change down to the zip code field.
    $response->assertOk()
        ->assertJsonPath('value', '')
        ->assertJsonPath('dependentShouldEmitChangesEvent', true);
});

it('keeps a value that is still valid', function (): void {
    syncCreationField('city_id', ['province_id' => 1, 'city_id' => 2])
        ->assertOk()
        ->assertJsonPath('value', 2);
});

it('resolves the third level of the chain', function (): void {
    $response = syncCreationField('zip_code', ['province_id' => 1, 'city_id' => 2]);

    $response->assertOk();

    expect(collect($response->json('options'))->pluck('value')->all())->toBe([33017]);
});

it('advertises the attributes it depends on', function (): void {
    syncCreationField('city_id', ['province_id' => 1])
        ->assertOk()
        ->assertJsonPath('dependsOn.province_id', 1);
});

it('serializes the parent values so an edit form can seed its first request', function (): void {
    syncCreationField('city_id', ['province_id' => 2])
        ->assertOk()
        ->assertJsonPath('ajaxSelect.parentValues.province_id', 2);
});

it('answers a search term sent through nova own sync request', function (): void {
    $response = test()->patchJson(
        '/nova-api/customers/creation-fields?'.http_build_query([
            'editing' => 'true',
            'editMode' => 'create',
            'field' => 'city_id',
            'component' => componentKey('city_id'),
        ]),
        ['province_id' => 1, AjaxSelectContext::SEARCH_KEY => 'Tar']
    );

    $response->assertOk();

    expect(collect($response->json('options'))->pluck('label')->all())->toBe(['Tarcento']);
});

it('never runs an options resolver for a guest', function (): void {
    auth()->forgetGuards();

    $response = syncCreationField('city_id', ['province_id' => 1]);

    // Nova's own middleware turns an unauthenticated API call away before the
    // field, and therefore the closure, is ever reached.
    expect($response->status())->not->toBe(200);
});

it('404s for an unknown component key', function (): void {
    test()->patchJson(
        '/nova-api/customers/creation-fields?'.http_build_query([
            'editing' => 'true',
            'editMode' => 'create',
            'field' => 'city_id',
            'component' => 'nope.nope.nope',
        ]),
        ['province_id' => 1]
    )->assertOk()->assertExactJson([]);
});

it('syncs an update form against the stored model', function (): void {
    $customer = Customer::create(['province_id' => 1, 'city_id' => 1]);

    test()->patchJson(
        "/nova-api/customers/{$customer->id}/update-fields?".http_build_query([
            'editing' => 'true',
            'editMode' => 'update',
            'field' => 'city_id',
            'component' => componentKey('city_id'),
        ]),
        ['province_id' => 1, 'city_id' => 1]
    )->assertOk()->assertJsonPath('value', 1);
});
