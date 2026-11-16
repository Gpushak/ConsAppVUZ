<?php

namespace App\Support;

use App\Models\Slot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class RoomOccupancy
{
    /**
     * Published slots in the same building and room whose time overlaps a day
     * this form would publish. Clocks are Asia/Yekaterinburg wall time, kept
     * on the app timezone the same way a saved slot stores them.
     *
     * @param  array<string, mixed>  $input
     * @return Collection<int, array{teacher: string, date: string, from: string, to: string}>
     */
    public static function conflicts(array $input): Collection
    {
        if (self::format($input) !== 'in_person') {
            return collect();
        }

        $building = is_string($input['building'] ?? null) ? $input['building'] : '';
        $room = trim((string) ($input['room'] ?? ''));

        if (! in_array($building, Building::LIST, true) || $room === '' || mb_strlen($room) > 32) {
            return collect();
        }

        $windows = self::windows($input);

        if ($windows === []) {
            return collect();
        }

        return Slot::query()
            ->where('is_published', true)
            ->where('building', $building)
            ->where('room', $room)
            ->where(function ($query) use ($windows) {
                foreach ($windows as [$starts, $ends]) {
                    $query->orWhere(function ($query) use ($starts, $ends) {
                        $query->where('starts_at', '<', $ends)
                            ->where('ends_at', '>', $starts);
                    });
                }
            })
            ->with('teacherProfile.user')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->map(function (Slot $slot) {
                $start = $slot->starts_at->copy()->shiftTimezone(DateSpan::TIMEZONE);
                $end = $slot->ends_at->copy()->shiftTimezone(DateSpan::TIMEZONE);
                $teacher = $slot->teacherProfile?->user?->name;

                if (! is_string($teacher) || $teacher === '') {
                    return null;
                }

                return [
                    'teacher' => $teacher,
                    'date' => $start->format('d.m.Y'),
                    'from' => $start->format('H:i'),
                    'to' => $end->format('H:i'),
                ];
            })
            ->filter()
            ->values();
    }

    private static function format(array $input): string
    {
        $format = $input['format'] ?? null;

        if ($format === null || $format === '') {
            return 'in_person';
        }

        return is_string($format) ? $format : '';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private static function windows(array $input): array
    {
        $date = self::day(is_string($input['date'] ?? null) ? $input['date'] : '');
        $startClock = self::clock(is_string($input['start_time'] ?? null) ? $input['start_time'] : '');
        $endClock = self::clock(is_string($input['end_time'] ?? null) ? $input['end_time'] : '');

        if ($date === null || $startClock === null || $endClock === null) {
            return [];
        }

        $everyWeeks = self::repeatWeeks($input);
        $last = $date->copy();

        if ($everyWeeks > 0) {
            $until = self::day(is_string($input['repeat_until'] ?? null) ? $input['repeat_until'] : '');

            if ($until === null || $until->lt($date)) {
                return [];
            }

            $last = $until;
        } elseif ($everyWeeks < 0) {
            return [];
        }

        $step = $everyWeeks === 2 ? 14 : 7;
        $cursor = $date->copy();
        $windows = [];

        while ($cursor->lessThanOrEqualTo($last) && count($windows) < 104) {
            $starts = self::atClock($cursor, $startClock);
            $ends = self::atClock($cursor, $endClock);

            if ($ends->greaterThan($starts) && $starts->greaterThan(now())) {
                $windows[] = [$starts, $ends];
            }

            if ($everyWeeks < 1) {
                break;
            }

            $cursor = $cursor->copy()->addDays($step);
        }

        return $windows;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function repeatWeeks(array $input): int
    {
        $repeat = $input['repeat'] ?? null;

        if ($repeat === '2' || $repeat === 2) {
            return 2;
        }

        if ($repeat === '1' || $repeat === 1) {
            return 1;
        }

        if ($repeat === null || $repeat === '' || $repeat === 'none') {
            $weekly = $input['repeat_weekly'] ?? null;

            return $weekly === true || $weekly === 1 || $weekly === '1' || $weekly === 'true' || $weekly === 'on'
                ? 1
                : 0;
        }

        return -1;
    }

    private static function day(string $value): ?Carbon
    {
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value) === 1) {
            $day = Carbon::createFromFormat('!d.m.Y', $value, DateSpan::TIMEZONE);

            if ($day === false || $day->format('d.m.Y') !== $value) {
                return null;
            }

            return $day->startOfDay();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $day = Carbon::createFromFormat('!Y-m-d', $value, DateSpan::TIMEZONE);

            if ($day === false || $day->format('Y-m-d') !== $value) {
                return null;
            }

            return $day->startOfDay();
        }

        return null;
    }

    private static function clock(string $value): ?string
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value) !== 1) {
            return null;
        }

        return substr($value, 0, 5);
    }

    private static function atClock(Carbon $day, string $clock): Carbon
    {
        return Carbon::parse($day->toDateString().' '.$clock, DateSpan::TIMEZONE)
            ->shiftTimezone((string) config('app.timezone'));
    }
}
