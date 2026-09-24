<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Nova;

use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Register the application's Nova resources.
     */
    protected function resources(): void
    {
        Nova::resources([
            CustomerResource::class,
        ]);
    }

    /**
     * Register the Nova gate.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', static fn ($user = null): bool => true);
    }

    protected function routes(): void
    {
        Nova::routes()->withAuthenticationRoutes()->register();
    }
}
