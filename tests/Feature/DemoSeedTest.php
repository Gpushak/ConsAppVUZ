<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use App\Support\Building;
use App\Support\DateSpan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Tests\ConsultationTestCase;

class DemoSeedTest extends ConsultationTestCase
{
    public function test_demo_consultations_cover_both_semesters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 09:00:00', 'Asia/Yekaterinburg'));
        $this->seed();

        $ivanova = User::query()->where('email', 'ivanova@kafedra.local')->firstOrFail();
        $petrov = User::query()->where('email', 'petrov@kafedra.local')->firstOrFail();
        $smirnova = User::query()->where('email', 'smirnova@kafedra.local')->firstOrFail();
        $sokolov = User::query()->where('email', 'sokolov@kafedra.local')->firstOrFail();

        $this->assertTrue(Hash::check(DatabaseSeeder::PASSWORD, $sokolov->password));
        $this->assertTrue(Hash::check(DatabaseSeeder::PASSWORD, $ivanova->password));
        $this->assertSame('Parol-123', DatabaseSeeder::PASSWORD);

        $favorites = $sokolov->studentProfile->favoriteTeachers()->pluck('teacher_profiles.id')->all();
        $this->assertEqualsCanonicalizing(
            [$ivanova->teacherProfile->id, $smirnova->teacherProfile->id],
            $favorites,
        );
        $this->assertNotContains($petrov->teacherProfile->id, $favorites);

        $slots = Slot::query()->with('bookings.studentProfile.user')->get();
        $this->assertCount(136, $slots);
        $this->assertSame(1, $slots->whereNotNull('conducted_at')->count());
        $this->assertSame(1, $slots->filter(fn (Slot $slot) => $slot->starts_at->lt(Carbon::parse('2026-11-16')))->count());

        $limit = Carbon::parse('2027-06-30 23:59:59', 'Asia/Yekaterinburg');
        foreach ($slots as $slot) {
            $end = $slot->ends_at->copy()->timezone('Asia/Yekaterinburg');
            $this->assertTrue($end->lessThanOrEqualTo($limit), $end->toDateTimeString());
            $this->assertTrue($slot->is_published);
        }

        $conducted = $this->teacherSlot($ivanova, '2026-11-10 10:00');
        $this->assertSame('10:45', $conducted->ends_at->format('H:i'));
        $this->assertTrue($conducted->conducted_at->equalTo($conducted->ends_at));
        $this->assertNull($conducted->series_id);
        $this->assertSame([Booking::COMPLETED], $conducted->bookings->pluck('status')->all());
        $this->assertSame(['Соколов Илья'], $this->studentNames($conducted));

        $this->assertSeries($this->atTime($ivanova, '10:00'), 33, 1, '2026-11-18', '2027-06-30', 'Главный А', '314');
        $this->assertSeries($this->atTime($ivanova, '16:00'), 16, 2, '2026-11-19', '2027-06-17', null, null);
        $this->assertSeries($this->atTime($smirnova, '15:00'), 17, 2, '2026-11-18', '2027-06-30', 'В', '105');
        $this->assertSeries($this->atTime($smirnova, '12:00'), 16, 2, '2026-11-25', '2027-06-23', 'Г', '220');
        $this->assertSeries($this->atTime($petrov, '12:00'), 33, 1, '2026-11-18', '2027-06-30', 'Б', '210');
        $this->assertSeries($this->atTime($petrov, '16:00'), 16, 2, '2026-11-20', '2027-06-18', null, null);

        $online = $this->atTime($ivanova, '16:00');
        $this->assertSame('16:45', $online->first()->ends_at->format('H:i'));
        $this->assertSame('https://meet.example.com/ivanova', $online->first()->meeting_url);
        $this->assertTrue($online->first()->isOnline());
        $this->assertSame(['Орлова Марина'], $this->studentNames($online->first()));
        $this->assertTrue($online->every(fn (Slot $slot) => $slot->meeting_url === 'https://meet.example.com/ivanova'));

        $this->assertSame(
            'https://meet.example.com/petrov',
            $this->atTime($petrov, '16:00')->first()->meeting_url,
        );
        $this->assertTrue($this->atTime($petrov, '16:00')->first()->isOnline());

        $this->assertEqualsCanonicalizing(
            Building::LIST,
            $slots->reject(fn (Slot $slot) => $slot->isOnline())->pluck('building')->unique()->values()->all(),
        );
        $this->assertTrue($slots->filter(fn (Slot $slot) => $slot->isOnline())->every(
            fn (Slot $slot) => $slot->building === null && $slot->room === null && $slot->hasMeetingLink(),
        ));

        $this->assertEqualsCanonicalizing(
            ['Соколов Илья', 'Белова Анна'],
            $this->studentNames($this->teacherSlot($ivanova, '2026-11-18 10:00')),
        );
        $draft = $this->teacherSlot($ivanova, '2026-11-24 14:00');
        $this->assertSame('принести черновик курсовой', $draft->comment);
        $this->assertNull($draft->series_id);
        $this->assertEqualsCanonicalizing(['Орлова Марина', 'Лебедева Ольга'], $this->studentNames($draft));

        $lab = $this->teacherSlot($smirnova, '2026-11-18 15:00');
        $this->assertSame('разбор лабораторной', $lab->comment);
        $this->assertEqualsCanonicalizing(['Волкова Дарья', 'Новиков Игорь'], $this->studentNames($lab));

        $group = $this->teacherSlot($ivanova, '2026-11-20 11:00');
        $this->assertNull($group->series_id);
        $this->assertEqualsCanonicalizing(
            ['Белова Анна', 'Соколов Илья', 'Морозов Кирилл'],
            $this->studentNames($group),
        );

        $free = $this->teacherSlot($petrov, '2027-06-30 12:00');
        $this->assertSame('12:45', $free->ends_at->format('H:i'));
        $this->assertSame('Б', $free->building);
        $this->assertSame('210', $free->room);
        $this->assertTrue($free->bookings->isEmpty());

        $this->assertNoDoubleBookings($slots);
        $this->assertNearWeeksHaveBookings();
        $this->assertEveryTeachingWeekHasASlot($slots, [
            $ivanova->teacherProfile->id => 'Иванова',
            $smirnova->teacherProfile->id => 'Смирнова',
            $petrov->teacherProfile->id => 'Петров',
        ]);

        $november = ['from' => '2026-11-16', 'to' => '2026-11-22'];
        $home = $this->actingAs($sokolov)->get(route('home', $november))->assertOk();
        $home->assertDontSee('Показать завершённые')
            ->assertDontSee('Скрыть завершённые')
            ->assertDontSee('10.11.2026')
            ->assertDontSee('>проведён<')
            ->assertSee('Иванова А. С.')
            ->assertSee('Смирнова Е. В.')
            ->assertDontSee('Петров')
            ->assertSee('18.11.2026')
            ->assertSee('19.11.2026')
            ->assertSee('20.11.2026')
            ->assertSee('https://meet.example.com/ivanova')
            ->assertSee('Вы записаны')
            ->assertSee('Записаться');

        $this->actingAs($sokolov)
            ->get(route('home', ['from' => '2026-11-09', 'to' => '2026-11-15']))
            ->assertOk()
            ->assertDontSee('data-slot-card', false)
            ->assertDontSee('>проведён<')
            ->assertDontSee('Показать завершённые');

        $this->actingAs($sokolov)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('Показать завершённые')
            ->assertDontSee('Скрыть завершённые')
            ->assertSee('10.11.2026')
            ->assertSee('18.11.2026')
            ->assertSee('data-finished', false);

        $this->actingAs($ivanova)
            ->get(route('teacher.slots.index', $november))
            ->assertOk()
            ->assertDontSee('Показать завершённые')
            ->assertDontSee('Скрыть завершённые')
            ->assertDontSee('>проведён<')
            ->assertDontSee('10.11.2026')
            ->assertSee('series-mark">каждую неделю', false)
            ->assertSee('18.11.2026')
            ->assertSee('2 студента');

        $this->actingAs($ivanova)
            ->get(route('slots.roster', $group))
            ->assertOk()
            ->assertSee('Белова Анна')
            ->assertSee('Соколов Илья')
            ->assertSee('Морозов Кирилл');

        foreach ([
            ['2027-03-15', '2027-03-21', '17.03.2027'],
            ['2027-06-14', '2027-06-20', '16.06.2027'],
        ] as [$from, $to, $label]) {
            $this->actingAs($sokolov)
                ->get(route('home', ['from' => $from, 'to' => $to]))
                ->assertOk()
                ->assertSee($label)
                ->assertSee('Иванова А. С.')
                ->assertSee('Смирнова Е. В.')
                ->assertDontSee('Петров')
                ->assertDontSee('>проведён<')
                ->assertSee('Вы записаны');

            $this->actingAs($ivanova)
                ->get(route('teacher.slots.index', ['from' => $from, 'to' => $to]))
                ->assertOk()
                ->assertSee($label)
                ->assertDontSee('>проведён<')
                ->assertDontSee('Показать завершённые');

            $this->actingAs($smirnova)
                ->get(route('teacher.slots.index', ['from' => $from, 'to' => $to]))
                ->assertOk()
                ->assertSee($label)
                ->assertDontSee('>проведён<');
        }

        $this->actingAs($petrov)
            ->get(route('teacher.slots.index', ['from' => '2027-06-14', 'to' => '2027-06-20']))
            ->assertOk()
            ->assertSee('16.06.2027')
            ->assertSee('свободен')
            ->assertDontSee('Показать завершённые')
            ->assertDontSee('>проведён<');
    }

    /**
     * @param  Collection<int, Slot>  $slots
     * @param  array<int, string>  $teachers
     */
    private function assertEveryTeachingWeekHasASlot(Collection $slots, array $teachers): void
    {
        $week = Carbon::parse('2026-11-16', DateSpan::TIMEZONE)->startOfDay();
        $last = Carbon::parse('2027-06-28', DateSpan::TIMEZONE)->startOfDay();
        $seen = 0;

        while ($week->lte($last)) {
            $from = $week->copy();
            $until = $week->copy()->addDays(6)->endOfDay();
            $seen++;

            foreach ($teachers as $teacherId => $name) {
                $hit = $slots->contains(function (Slot $slot) use ($teacherId, $from, $until) {
                    if ($slot->teacher_profile_id !== $teacherId || ! $slot->is_published || $slot->conducted_at !== null) {
                        return false;
                    }

                    $local = $slot->starts_at->copy()->timezone(DateSpan::TIMEZONE);

                    return $local->greaterThanOrEqualTo($from) && $local->lessThanOrEqualTo($until);
                });
                $this->assertTrue($hit, $name.' '.$from->toDateString());
            }

            $week->addDays(7);
        }

        $this->assertSame(33, $seen);
    }

    /**
     * @param  Collection<int, Slot>  $series
     */
    private function assertSeries(
        Collection $series,
        int $count,
        int $weeks,
        string $firstDay,
        string $lastDay,
        ?string $building,
        ?string $room,
    ): void {
        $this->assertCount($count, $series);
        $this->assertCount(1, $series->pluck('series_id')->unique());
        $this->assertNotNull($series->first()->series_id);
        $this->assertSame($firstDay.' '.$series->first()->starts_at->format('H:i'), $series->first()->starts_at->format('Y-m-d H:i'));
        $this->assertSame($lastDay, $series->last()->starts_at->format('Y-m-d'));
        $this->assertTrue($series->every(fn (Slot $slot) => (int) $slot->series_weeks === $weeks));
        $this->assertSame($building, $series->first()->building);
        $this->assertSame($room, $series->first()->room);

        $step = ($weeks === 2 ? 14 : 7) * 86400;
        $rows = $series->values();

        for ($index = 1; $index < $rows->count(); $index++) {
            $this->assertSame(
                $step,
                $rows[$index]->starts_at->getTimestamp() - $rows[$index - 1]->starts_at->getTimestamp(),
            );
        }
    }

    /**
     * @param  Collection<int, Slot>  $slots
     */
    private function assertNoDoubleBookings(Collection $slots): void
    {
        $bookings = $slots->flatMap(fn (Slot $slot) => $slot->bookings)->groupBy('student_profile_id');

        foreach ($bookings as $rows) {
            $this->assertSame($rows->count(), $rows->pluck('slot_id')->unique()->count());

            $active = $rows
                ->filter(fn (Booking $booking) => $booking->status === Booking::BOOKED)
                ->sortBy(fn (Booking $booking) => $booking->slot->starts_at->getTimestamp())
                ->values();

            for ($index = 1; $index < $active->count(); $index++) {
                $this->assertTrue(
                    $active[$index]->slot->starts_at->greaterThanOrEqualTo($active[$index - 1]->slot->ends_at),
                    $active[$index]->slot->starts_at->toDateTimeString(),
                );
            }
        }
    }

    private function assertNearWeeksHaveBookings(): void
    {
        $bookings = Booking::query()->with('slot')->where('status', Booking::BOOKED)->get();

        foreach (['2026-11-16', '2026-11-23', '2026-11-30', '2026-12-07'] as $monday) {
            $start = Carbon::parse($monday.' 00:00:00');
            $end = $start->copy()->addDays(7);
            $count = $bookings->filter(function (Booking $booking) use ($start, $end) {
                return $booking->slot->starts_at->greaterThanOrEqualTo($start)
                    && $booking->slot->starts_at->lessThan($end);
            })->count();

            $this->assertGreaterThan(0, $count, $monday);
        }
    }

    /**
     * @return Collection<int, Slot>
     */
    private function atTime(User $teacher, string $time): Collection
    {
        return Slot::query()
            ->with('bookings.studentProfile.user')
            ->where('teacher_profile_id', $teacher->teacherProfile->id)
            ->whereNotNull('series_id')
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Slot $slot) => $slot->starts_at->format('H:i') === $time)
            ->values();
    }

    private function teacherSlot(User $teacher, string $start): Slot
    {
        return Slot::query()
            ->with('bookings.studentProfile.user')
            ->where('teacher_profile_id', $teacher->teacherProfile->id)
            ->where('starts_at', $start.':00')
            ->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function studentNames(Slot $slot): array
    {
        return $slot->bookings
            ->map(fn (Booking $booking) => $booking->studentProfile->user->name)
            ->values()
            ->all();
    }
}
