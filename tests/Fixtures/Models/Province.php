<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
