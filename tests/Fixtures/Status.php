<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
