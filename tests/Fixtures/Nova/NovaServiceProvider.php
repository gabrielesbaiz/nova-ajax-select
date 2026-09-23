<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Nova;

use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Register resources explicitly: the parent implementation scans an
     * app/Nova directory that does not exist under Testbench.
     */
    protected function resources(): void
    {
        Nova::resources([
            CustomerResource::class,
        ]);
    }

    /**
     * Without a viewNova gate Nova denies every request outside local, and the
     * denial renders the full layout instead of returning JSON.
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
