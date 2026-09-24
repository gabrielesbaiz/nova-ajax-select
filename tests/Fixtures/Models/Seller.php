<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seller extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }
}
