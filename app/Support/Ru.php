<?php

namespace App\Support;

final class Ru
{
    public static function students(int $count): string
    {
        return $count.' '.self::plural($count, 'студент', 'студента', 'студентов');
    }

    public static function aheadDays(int $count): string
    {
        return 'впереди ещё '.$count.' '.self::plural($count, 'день', 'дня', 'дней');
    }

    public static function aheadDaysSentence(int $count): string
    {
        if ($count < 1) {
            return 'Будущих дней не осталось.';
        }

        $phrase = self::aheadDays($count);

        return mb_strtoupper(mb_substr($phrase, 0, 1)).mb_substr($phrase, 1).'.';
    }

    public static function emptyDaysWillBeUnpublished(int $count): string
    {
        if ($count < 1) {
            return 'Пустых будущих дней для снятия не осталось.';
        }

        if (self::form($count) === 'one') {
            return 'Будет снят с публикации 1 пустой будущий день.';
        }

        $words = self::form($count) === 'few' ? 'пустых будущих дня' : 'пустых будущих дней';

        return 'Будут сняты с публикации '.$count.' '.$words.'.';
    }

    private static function plural(int $count, string $one, string $few, string $many): string
    {
        return match (self::form($count)) {
            'one' => $one,
            'few' => $few,
            default => $many,
        };
    }

    private static function form(int $count): string
    {
        $mod100 = abs($count) % 100;
        $mod10 = $mod100 % 10;

        if ($mod100 > 10 && $mod100 < 20) {
            return 'many';
        }

        if ($mod10 === 1) {
            return 'one';
        }

        if ($mod10 >= 2 && $mod10 <= 4) {
            return 'few';
        }

        return 'many';
    }
}
