<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class Term
{
    public const WORK_ENDS_ON = '2027-06-30';

    public static function workEndsOn(): Carbon
    {
        return Carbon::parse(self::WORK_ENDS_ON)->startOfDay();
    }
}
