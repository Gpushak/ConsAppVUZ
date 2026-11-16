<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class SeriesCadence
{
    public static function stepDays(int $everyWeeks): int
    {
        return $everyWeeks === 2 ? 14 : 7;
    }

    public static function fits(Carbon $start, Carbon $until, int $everyWeeks): bool
    {
        $days = self::distance($start, $until);

        return $days >= 0 && $days % self::stepDays($everyWeeks) === 0;
    }

    public static function rejection(Carbon $start, int $everyWeeks): string
    {
        if ($everyWeeks === 2) {
            return 'Дата окончания — через 14, 28 и далее дней от даты слота. День через 7 дней не подходит.';
        }

        $weekday = DateSpan::weekday($start->copy()->timezone(DateSpan::TIMEZONE));

        return 'Дата окончания — тот же день недели, что и дата слота: '.$weekday.'.';
    }

    private static function distance(Carbon $start, Carbon $until): int
    {
        $from = self::civil($start);
        $to = self::civil($until);

        return intdiv($to->getTimestamp() - $from->getTimestamp(), 86400);
    }

    private static function civil(Carbon $day): Carbon
    {
        return Carbon::createFromFormat(
            '!Y-m-d',
            $day->copy()->timezone(DateSpan::TIMEZONE)->toDateString(),
            DateSpan::TIMEZONE,
        )->startOfDay();
    }
}
