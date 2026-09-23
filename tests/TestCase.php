<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Tests;

use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\AjaxSelectServiceProvider;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\ServiceProvider;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaCoreServiceProvider;
use Laravel\Nova\NovaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Nova keeps its registered scripts, styles and translations in static
        // properties, so without a flush one test's assets leak into the next.
        // Assets are registered lazily on ServingNova, so this loses nothing.
        Nova::flushState();

        $this->migrateFixtures();
    }

    protected function tearDown(): void
    {
        Nova::flushState();
        AjaxSelect::flushLabelMemo();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            // Nova renders through Inertia; without its provider the container
            // cannot resolve Inertia\Ssr\Gateway during a request.
            ServiceProvider::class,
            NovaCoreServiceProvider::class,
            NovaServiceProvider::class,
            AjaxSelectServiceProvider::class,
            Fixtures\Nova\NovaServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('nova.storage_disk', 'local');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function migrateFixtures(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
        });

        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('province_id');
            $table->string('name');
            $table->string('zip_code')->nullable();
        });

        Schema::create('dealers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('sellers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dealer_id');
            $table->string('name');
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->foreignId('province_id')->nullable();
            $table->foreignId('city_id')->nullable();
            $table->string('zip_code')->nullable();
            $table->foreignId('dealer_id')->nullable();
            $table->foreignId('seller_id')->nullable();
            $table->string('status')->nullable();
        });
    }
}
