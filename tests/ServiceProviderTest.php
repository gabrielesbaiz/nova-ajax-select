<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\AjaxSelectServiceProvider;
use Gabrielesbaiz\NovaAjaxSelect\NovaAjaxSelectServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

it('registers its script under a handle that cannot collide with the upstream package', function (): void {
    event(new ServingNova(app(), request()));

    $names = collect(Nova::allScripts())->map(fn ($asset) => $asset->name())->all();

    expect($names)->toContain('gabrielesbaiz-nova-ajax-select')
        // Nova serves scripts by name with "first", so a handle shared with
        // alexwenzel/ajax-select would leave one bundle unloaded.
        ->and($names)->not->toContain('ajax-select');
});

it('points the script at a bundle that exists', function (): void {
    event(new ServingNova(app(), request()));

    $asset = collect(Nova::allScripts())->first(fn ($asset) => $asset->name() === 'gabrielesbaiz-nova-ajax-select');

    expect(file_exists($asset->path()))->toBeTrue();
});

it('registers its translations', function (): void {
    event(new ServingNova(app(), request()));

    expect(Nova::allTranslations())->toHaveKey('Could not load the available options.');
});

it('merges its configuration', function (): void {
    expect(config('nova-ajax-select.search.limit'))->toBe(50)
        ->and(config('nova-ajax-select.validation.enabled'))->toBeTrue()
        ->and(config('nova-ajax-select.cache.prefix'))->toBe('nova-ajax-select');
});

it('still resolves the deprecated provider name', function (): void {
    expect(new NovaAjaxSelectServiceProvider(app()))->toBeInstanceOf(AjaxSelectServiceProvider::class);
});

it('is still served when the upstream package has claimed the ajax-select handle', function (): void {
    // Mimic alexwenzel/ajax-select, which registers first in a real app.
    Nova::script('ajax-select', __DIR__.'/../dist/js/field.js');

    event(new ServingNova(app(), request()));

    $resolved = collect(Nova::allScripts())
        ->filter(fn ($asset) => $asset->name() === 'gabrielesbaiz-nova-ajax-select')
        ->first();

    // Nova serves by name with "first", so a shared handle hides one bundle.
    expect($resolved)->not->toBeNull();
});

it('registers vue components under the names nova derives from the field component', function (): void {
    $component = (new AjaxSelect('City'))->component;

    $bundle = file_get_contents(__DIR__.'/../dist/js/field.js');

    foreach (['form', 'detail', 'index'] as $prefix) {
        // Nova capitalizes and camelizes a component name before looking it
        // up, so the bundle registers the PascalCase spelling.
        $pascal = str_replace(' ', '', ucwords(str_replace('-', ' ', "{$prefix}-{$component}")));

        expect($bundle)->toContain($pascal);
    }
});
