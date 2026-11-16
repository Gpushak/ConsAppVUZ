<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class MonthGrid
{
    /**
     * @param  array<string, true>  $marked
     * @return list<list<array{date: Carbon, inMonth: bool, marked: bool, today: bool}>>
     */
    public static function weeks(Carbon $month, array $marked): array
    {
        $first = $month->copy()->timezone(DateSpan::TIMEZONE)->startOfMonth();
        $cursor = $first->copy()->startOfWeek(Carbon::MONDAY);
        $last = $first->copy()->endOfMonth()->startOfDay()->endOfWeek(Carbon::SUNDAY);
        $today = Carbon::now(DateSpan::TIMEZONE)->toDateString();
        $weeks = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $week = [];

            for ($day = 0; $day < 7; $day++) {
                $key = $cursor->toDateString();
                $week[] = [
                    'date' => $cursor->copy(),
                    'inMonth' => $cursor->month === $first->month && $cursor->year === $first->year,
                    'marked' => isset($marked[$key]),
                    'today' => $key === $today,
                ];
                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return $weeks;
    }

    public static function parse(mixed $value): Carbon
    {
        $current = Carbon::now(DateSpan::TIMEZONE)->startOfMonth();

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}$/', $value) !== 1) {
            return $current;
        }

        $month = Carbon::createFromFormat('!Y-m', $value, DateSpan::TIMEZONE);

        if ($month === false || $month->format('Y-m') !== $value) {
            return $current;
        }

        return $month->startOfMonth();
    }

    public static function title(Carbon $month): string
    {
        $names = [
            1 => 'январь',
            2 => 'февраль',
            3 => 'март',
            4 => 'апрель',
            5 => 'май',
            6 => 'июнь',
            7 => 'июль',
            8 => 'август',
            9 => 'сентябрь',
            10 => 'октябрь',
            11 => 'ноябрь',
            12 => 'декабрь',
        ];

        $name = $names[$month->month];

        return mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1).' '.$month->year;
    }
}
