<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaAjaxSelect\Support\Option;
use Gabrielesbaiz\NovaAjaxSelect\Support\OptionCollection;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\City;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Status;

it('normalizes a value => label map', function (): void {
    expect(OptionCollection::make(['it' => 'Italy', 'fr' => 'France'])->serialize())
        ->toBe([
            ['value' => 'it', 'label' => 'Italy'],
            ['value' => 'fr', 'label' => 'France'],
        ]);
});

it('promotes the legacy display key to label', function (): void {
    $options = OptionCollection::make([
        ['value' => 1, 'display' => 'Udine'],
        ['value' => 2, 'display' => 'Trieste'],
    ]);

    expect($options->serialize())->toBe([
        ['value' => 1, 'label' => 'Udine'],
        ['value' => 2, 'label' => 'Trieste'],
    ]);
});

it('prefers label over display when both are present', function (): void {
    expect(OptionCollection::make([['value' => 1, 'label' => 'Real', 'display' => 'Legacy']])->serialize())
        ->toBe([['value' => 1, 'label' => 'Real']]);
});

it('keeps group, subtitle and disabled', function (): void {
    expect(OptionCollection::make([
        ['value' => 1, 'label' => 'Udine', 'group' => 'FVG', 'subtitle' => 'IT', 'disabled' => true],
    ])->serialize())->toBe([
        ['value' => 1, 'label' => 'Udine', 'group' => 'FVG', 'subtitle' => 'IT', 'disabled' => true],
    ]);
});

it('casts numeric keys to integers like nova does', function (): void {
    $options = OptionCollection::make(['12' => 'Udine']);

    expect($options->serialize()[0]['value'])->toBe(12)
        ->and($options->has(12))->toBeTrue()
        ->and($options->has('12'))->toBeTrue();
});

it('does not cast non canonical numeric strings', function (): void {
    expect(OptionCollection::make([['value' => '007', 'label' => 'Bond']])->serialize()[0]['value'])
        ->toBe('007');
});

it('normalizes models, enums and scalars', function (): void {
    $city = new City(['id' => 5, 'name' => 'Udine']);
    $city->id = 5;

    expect(OptionCollection::make([$city])->serialize())->toBe([['value' => 5, 'label' => 'Udine']])
        ->and(OptionCollection::make(Status::cases())->values())->toBe(['draft', 'published', 'archived'])
        // A plain list uses its keys as values, exactly as Nova's Select does.
        ->and(OptionCollection::make(['Alpha', 'Beta'])->serialize())
        ->toBe([['value' => 0, 'label' => 'Alpha'], ['value' => 1, 'label' => 'Beta']]);
});

it('accepts an enum class string', function (): void {
    expect(OptionCollection::make(Status::class)->values())->toBe(['draft', 'published', 'archived']);
});

it('is empty for null', function (): void {
    expect(OptionCollection::make(null)->isEmpty())->toBeTrue();
});

it('filters by search case insensitively', function (): void {
    $options = OptionCollection::make([1 => 'Udine', 2 => 'Trieste', 3 => 'Pordenone']);

    expect($options->search('ine')->values())->toBe([1])
        ->and($options->search('E')->count())->toBe(3)
        ->and($options->search(null)->count())->toBe(3);
});

it('limits the option set', function (): void {
    expect(OptionCollection::make([1 => 'a', 2 => 'b', 3 => 'c'])->take(2)->values())->toBe([1, 2])
        ->and(OptionCollection::make([1 => 'a'])->take(null)->count())->toBe(1);
});

it('prepends a missing selected option without duplicating it', function (): void {
    $options = OptionCollection::make([2 => 'Trieste']);
    $selected = new Option(1, 'Udine');

    expect($options->prepend($selected)->values())->toBe([1, 2])
        ->and($options->prepend($selected)->prepend($selected)->values())->toBe([1, 2])
        ->and($options->prepend(null)->values())->toBe([2]);
});

it('looks labels up by loosely typed value', function (): void {
    $options = OptionCollection::make([12 => 'Udine']);

    expect($options->labelFor('12'))->toBe('Udine')
        ->and($options->labelFor(12))->toBe('Udine')
        ->and($options->labelFor(null))->toBeNull()
        ->and($options->labelFor(99))->toBeNull();
});
