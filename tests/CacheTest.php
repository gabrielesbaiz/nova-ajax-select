<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\City;
use Illuminate\Support\Facades\Cache;
use Laravel\Nova\Http\Requests\NovaRequest;

function cacheRequest(array $input = []): NovaRequest
{
    $request = NovaRequest::create('/nova-api/customers/creation-fields', 'PATCH', array_merge($input, [
        'editing' => 'true',
        'editMode' => 'create',
    ]));

    app()->instance(NovaRequest::class, $request);

    return $request;
}

function contextFor(AjaxSelect $field): AjaxSelectContext
{
    return $field->currentContext();
}

beforeEach(fn () => AjaxSelect::flushLabelMemo());

it('does not touch the cache unless asked', function (): void {
    cacheRequest();

    $calls = 0;

    $field = AjaxSelect::make('City')->options(function () use (&$calls) {
        $calls++;

        return ['a' => 'A'];
    });

    $field->resolveOptions($field->currentContext());
    Cache::flush();

    expect(Cache::getStore()->get($field->optionsCacheKey($field->currentContext())))->toBeNull()
        ->and($calls)->toBe(1);
});

it('serves a cached option set on the next resolve', function (): void {
    cacheRequest();

    $calls = 0;
    $resolve = function () use (&$calls) {
        $calls++;

        return ['a' => 'A'];
    };

    AjaxSelect::make('City')->options($resolve)->cacheFor(60)->jsonSerialize();
    AjaxSelect::make('City')->options($resolve)->cacheFor(60)->jsonSerialize();

    expect($calls)->toBe(1);
});

it('varies the cache key by parent value, limit, locale and scope', function (): void {
    $request = cacheRequest(['region_id' => 1]);

    $field = AjaxSelect::make('City', 'city_id')->parent('region_id')->options(fn () => []);
    $base = $field->optionsCacheKey(AjaxSelectContext::forRequest($field, $request));

    $other = AjaxSelect::make('City', 'city_id')->parent('region_id')->options(fn () => []);
    $otherKey = $other->optionsCacheKey(AjaxSelectContext::forRequest($other, cacheRequest(['region_id' => 2])));

    expect($otherKey)->not->toBe($base);

    app()->setLocale('it');
    $localeKey = $field->optionsCacheKey(AjaxSelectContext::forRequest($field, cacheRequest(['region_id' => 1])));
    app()->setLocale('en');

    expect($localeKey)->not->toBe($base);

    $scoped = AjaxSelect::make('City', 'city_id')->parent('region_id')->options(fn () => [])
        ->cacheScope(fn () => 'tenant-2');
    $scopedKey = $scoped->optionsCacheKey(AjaxSelectContext::forRequest($scoped, cacheRequest(['region_id' => 1])));

    expect($scopedKey)->not->toBe($base);
});

it('gives two different closures different cache keys', function (): void {
    $request = cacheRequest();

    $a = AjaxSelect::make('A', 'a')->options(fn () => ['x' => 'X']);
    $b = AjaxSelect::make('A', 'a')->options(fn () => ['y' => 'Y']);

    expect($a->optionsCacheKey(AjaxSelectContext::forRequest($a, $request)))
        ->not->toBe($b->optionsCacheKey(AjaxSelectContext::forRequest($b, $request)));
});

it('bypasses the cache for searches by default', function (): void {
    $calls = 0;
    $resolve = function () use (&$calls) {
        $calls++;

        return ['a' => 'Alpha'];
    };

    foreach (['al', 'alp'] as $search) {
        $request = NovaRequest::create('/nova-api/customers/creation-fields', 'PATCH', [
            'editing' => 'true',
            'editMode' => 'create',
            AjaxSelectContext::SEARCH_KEY => $search,
        ]);
        app()->instance(NovaRequest::class, $request);

        AjaxSelect::make('City')->options($resolve)->asyncSearchable()->cacheFor(60)->jsonSerialize();
    }

    expect($calls)->toBe(2);
});

it('honours withoutCache even when the config enables caching', function (): void {
    config()->set('nova-ajax-select.cache.enabled', true);
    cacheRequest();

    $calls = 0;
    $resolve = function () use (&$calls) {
        $calls++;

        return ['a' => 'A'];
    };

    AjaxSelect::make('City')->options($resolve)->withoutCache()->jsonSerialize();
    AjaxSelect::make('City')->options($resolve)->withoutCache()->jsonSerialize();

    expect($calls)->toBe(2);
});

it('caches a model backed option set', function (): void {
    cacheRequest();

    City::create(['region_id' => 1, 'name' => 'Udine']);

    AjaxSelect::make('City')->optionsFromModel(City::class)->cacheFor(60)->jsonSerialize();

    City::query()->delete();

    $options = AjaxSelect::make('City')->optionsFromModel(City::class)->cacheFor(60)->jsonSerialize()['options'];

    expect($options)->toBe([['value' => 1, 'label' => 'Udine']]);
});

it('accepts a DateTimeInterface ttl and a named store', function (): void {
    cacheRequest();

    City::create(['region_id' => 1, 'name' => 'Toronto']);

    $field = AjaxSelect::make('City')
        ->optionsFromModel(City::class)
        ->cacheFor(now()->addDay(), store: 'array');

    expect($field->jsonSerialize()['options'])->toHaveCount(1);
});
