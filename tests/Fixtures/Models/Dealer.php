<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dealer extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function sellers(): HasMany
    {
        return $this->hasMany(Seller::class);
    }
}
