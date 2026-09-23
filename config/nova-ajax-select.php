<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Option caching
    |--------------------------------------------------------------------------
    |
    | Defaults for fields that do not call cacheFor() themselves. The cache key
    | is NOT scoped to the authenticated user or tenant, because that would
    | destroy the hit rate. If a field's options depend on who is asking, give
    | it an explicit ->cacheScope().
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
    | Defaults for asyncSearchable() fields. "limit" also caps how many options
    | a non-searchable field may serialize into the form payload.
    |
    */

    'search' => [
        'min_length' => 0,
        'limit' => 50,
        'debounce' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Option validation
    |--------------------------------------------------------------------------
    |
    | Automatically reject submitted values that are not among the resolved
    | options. Set to false to disable it application-wide.
    |
    */

    'validation' => [
        'enabled' => true,
    ],

];
