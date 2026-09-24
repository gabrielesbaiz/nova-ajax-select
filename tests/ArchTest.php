<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaAjaxSelect\Contracts\OptionSource;

arch('it does not leave debugging helpers behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('every source implements the contract')
    ->expect('Gabrielesbaiz\NovaAjaxSelect\Sources')
    ->toImplement(OptionSource::class)
    ->toBeFinal();

arch('support and rule types are final')
    ->expect(['Gabrielesbaiz\NovaAjaxSelect\Support', 'Gabrielesbaiz\NovaAjaxSelect\Rules'])
    ->toBeFinal();

arch('concerns are traits')
    ->expect('Gabrielesbaiz\NovaAjaxSelect\Concerns')
    ->toBeTraits();

arch('the package declares strict types')
    ->expect('Gabrielesbaiz\NovaAjaxSelect')
    ->toUseStrictTypes();
