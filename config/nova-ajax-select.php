<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Option Caching
    |--------------------------------------------------------------------------
    |
    | Here you may configure the caching defaults used by fields that do not
    | call cacheFor() themselves. Cache keys are not scoped to the current
    | user or tenant, so per-user options should declare a cache scope.
    |
    */

    'cache' => [
        'enabled' => false,
        'store' => null,
        'ttl' => 300,
        'prefix' => 'nova-ajax-select',
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | The following options configure the defaults used by fields that are
    | marked as asynchronously searchable. The "limit" option also caps
    | how many options any field may serialize into a form payload.
    |
    */

    'search' => [
        'min_length' => 0,
        'limit' => 50,
        'debounce' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Option Validation
    |--------------------------------------------------------------------------
    |
    | This option determines whether submitted values are automatically
    | rejected when they are not among the field's resolved options.
    | Disabling it turns the additional validation rule off entirely.
    |
    */

    'validation' => [
        'enabled' => true,
    ],

];
