<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Favorite extends Pivot
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'favorites';
}
