<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    public $timestamps = false;

    protected $guarded = [];
}
