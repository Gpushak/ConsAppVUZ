<?php

namespace App\Support;

final class Ru
{
    public static function students(int $count): string
    {
        $mod100 = abs($count) % 100;
        $mod10 = $mod100 % 10;

        if ($mod100 > 10 && $mod100 < 20) {
            $word = 'студентов';
        } elseif ($mod10 === 1) {
            $word = 'студент';
        } elseif ($mod10 >= 2 && $mod10 <= 4) {
            $word = 'студента';
        } else {
            $word = 'студентов';
        }

        return $count.' '.$word;
    }
}
