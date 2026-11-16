<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class DateSpan
{
    public const TIMEZONE = 'Asia/Yekaterinburg';

    public const ORDER_ERROR = 'Дата «По» раньше даты «С».';

    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $until,
        public readonly ?string $error = null,
        public readonly bool $bounded = true,
    ) {}

    public static function currentWeek(): self
    {
        $today = Carbon::now(self::TIMEZONE)->startOfDay();
        $from = $today->copy()->startOfWeek(Carbon::MONDAY);
        $until = $from->copy()->addDays(6)->endOfDay();

        return new self($from, $until);
    }

    public static function fromQuery(mixed $from, mixed $to): self
    {
        $from = is_string($from) ? $from : null;
        $to = is_string($to) ? $to : null;

        if (($from === null || $from === '') && ($to === null || $to === '')) {
            return self::currentWeek();
        }

        $start = self::parseDate($from);
        $end = self::parseDate($to);

        if ($start === null || $end === null) {
            $week = self::currentWeek();

            return new self($week->from, $week->until, 'Укажите даты «С» и «По».', false);
        }

        if ($end->lt($start)) {
            return new self($start, $end->copy()->endOfDay(), self::ORDER_ERROR, false);
        }

        return new self($start, $end->copy()->endOfDay());
    }

    public function previous(): self
    {
        return $this->shift(-7);
    }

    public function next(): self
    {
        return $this->shift(7);
    }

    /**
     * @return array{from: string, to: string}
     */
    public function query(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->until->copy()->timezone(self::TIMEZONE)->toDateString(),
        ];
    }

    public function contains(Carbon $moment): bool
    {
        $local = $moment->copy()->timezone(self::TIMEZONE);

        return $local->greaterThanOrEqualTo($this->from) && $local->lessThanOrEqualTo($this->until);
    }

    /**
     * @param  Collection<int, mixed>  $slots
     * @return Collection<int, array{date: Carbon, slots: Collection<int, mixed>}>
     */
    public function buckets(Collection $slots): Collection
    {
        $grouped = $slots->groupBy(function ($slot): string {
            return $slot->starts_at->copy()->timezone(self::TIMEZONE)->toDateString();
        });

        $pack = function (Collection $daySlots): Collection {
            return $daySlots->sort(function ($left, $right): int {
                return $left->starts_at->getTimestamp() <=> $right->starts_at->getTimestamp()
                    ?: $left->id <=> $right->id;
            })->values();
        };

        if (! $this->bounded) {
            return $grouped->keys()->sort()->values()->map(function (string $key) use ($grouped, $pack) {
                return [
                    'date' => Carbon::parse($key, self::TIMEZONE)->startOfDay(),
                    'slots' => $pack($grouped->get($key, collect())),
                ];
            });
        }

        return $this->days()->map(function (Carbon $day) use ($grouped, $pack) {
            $key = $day->toDateString();

            return [
                'date' => $day,
                'slots' => $pack($grouped->get($key, collect())),
            ];
        });
    }

    public static function parseDay(mixed $value): ?Carbon
    {
        return self::parseDate(is_string($value) ? $value : null);
    }

    public static function weekday(Carbon $day): string
    {
        return [
            1 => 'понедельник',
            2 => 'вторник',
            3 => 'среда',
            4 => 'четверг',
            5 => 'пятница',
            6 => 'суббота',
            7 => 'воскресенье',
        ][$day->dayOfWeekIso];
    }

    /**
     * @return Collection<int, Carbon>
     */
    public function days(): Collection
    {
        $days = collect();
        $cursor = $this->from->copy()->timezone(self::TIMEZONE)->startOfDay();
        $last = $this->until->copy()->timezone(self::TIMEZONE)->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $days->push($cursor->copy());
            $cursor->addDay();
        }

        return $days;
    }

    private function shift(int $days): self
    {
        $from = $this->from->copy()->timezone(self::TIMEZONE)->addDays($days)->startOfDay();
        $untilDay = $this->until->copy()->timezone(self::TIMEZONE)->addDays($days)->startOfDay();
        $inverted = $untilDay->lt($from);

        return new self(
            $from,
            $untilDay->copy()->endOfDay(),
            $inverted ? self::ORDER_ERROR : null,
            ! $inverted,
        );
    }

    private static function parseDate(?string $value): ?Carbon
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value, self::TIMEZONE);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date->startOfDay();
    }
}
