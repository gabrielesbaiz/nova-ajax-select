<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect;

use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Nova;

class AjaxSelectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nova-ajax-select.php', 'nova-ajax-select');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/nova-ajax-select.php' => config_path('nova-ajax-select.php'),
        ], 'nova-ajax-select-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/nova-ajax-select'),
        ], 'nova-ajax-select-lang');

        Nova::serving(function (): void {
            // Later calls win, so the application's own overrides come last.
            Nova::translations(__DIR__.'/../lang/en.json');
            Nova::translations(__DIR__.'/../lang/'.app()->getLocale().'.json');
            Nova::translations(lang_path('vendor/nova-ajax-select/'.app()->getLocale().'.json'));

            // The handle is the public URL segment and must not collide with
            // alexwenzel/ajax-select: Nova resolves scripts by name with
            // ->first(), so a duplicate handle means one bundle is never served.
            Nova::script('gabrielesbaiz-nova-ajax-select', __DIR__.'/../dist/js/field.js');
        });
    }
}
