<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use App\Services\BookingService;
use Illuminate\Support\Carbon;
use Tests\ConsultationTestCase;

class SeriesRosterTest extends ConsultationTestCase
{
    public function test_weekly_series_runs_through_the_end_date_and_skips_overlap_and_past_days(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');

        $past = app(BookingService::class)->publish($teacher->teacherProfile, [
            'starts_at' => Carbon::parse('2026-10-18 10:00:00'),
            'ends_at' => Carbon::parse('2026-10-18 10:45:00'),
            'building' => 'Б',
            'room' => '12',
            'comment' => 'черновик',
            'meeting_url' => null,
        ], Carbon::parse('2026-11-01'));

        $pastDays = $past['created']->map(fn (Slot $slot) => $slot->starts_at->toDateString())->all();
        $this->assertSame(['2026-10-25', '2026-11-01'], $pastDays);
        $this->assertNull(Slot::query()->whereDate('starts_at', '2026-10-18')->first());
        $this->assertNotNull($past['created']->first()->series_id);
        $this->assertSame('черновик', $past['created']->last()->comment);
        $this->assertCount(1, $past['created']->pluck('series_id')->unique());

        Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => '2026-11-03 11:00:00',
            'ends_at' => '2026-11-03 11:30:00',
            'building' => 'А',
            'room' => '5',
            'is_published' => true,
        ]);

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => '27.10.2026',
            'start_time' => '11:00',
            'end_time' => '11:45',
            'building' => 'А',
            'room' => '101',
            'comment' => 'каждую неделю',
            'repeat_weekly' => '1',
            'repeat_until' => '17.11.2026',
        ])->assertRedirect(route('teacher.slots.index'))
            ->assertSessionHas('status', 'Опубликовано слотов: 3. Дни с пересечением пропущены.');

        $series = Slot::query()
            ->where('room', '101')
            ->orderBy('starts_at')
            ->get();

        $this->assertSame(
            ['2026-10-27', '2026-11-10', '2026-11-17'],
            $series->map(fn (Slot $slot) => $slot->starts_at->toDateString())->all(),
        );
        $this->assertCount(1, $series->pluck('series_id')->filter()->unique());
        $this->assertTrue($series->every(fn (Slot $slot) => $slot->comment === 'каждую неделю'));
        $this->assertTrue($series->every(fn (Slot $slot) => $slot->starts_at->format('H:i') === '11:00'));
        $this->assertNull(Slot::query()->where('room', '5')->firstOrFail()->series_id);
        $this->assertNull(Slot::query()->whereDate('starts_at', '2026-11-24')->where('room', '101')->first());

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => '28.10.2026',
            'start_time' => '12:00',
            'end_time' => '12:45',
            'building' => 'В',
            'room' => '8',
        ])->assertRedirect(route('teacher.slots.index'));

        $this->assertNull(Slot::query()->where('room', '8')->firstOrFail()->series_id);

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => '12.04.2027',
            'start_time' => '09:00',
            'end_time' => '09:45',
            'building' => 'Г',
            'room' => '1',
            'repeat_weekly' => '1',
            'repeat_until' => '20.04.2027',
        ])->assertSessionHasErrors('repeat_until');

        $this->assertNull(Slot::query()->where('room', '1')->first());

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => '12.04.2027',
            'start_time' => '09:00',
            'end_time' => '09:45',
            'building' => 'Г',
            'room' => '1',
            'repeat_weekly' => '1',
            'repeat_until' => '19.04.2027',
        ])->assertRedirect(route('teacher.slots.index'));

        $this->assertSame(
            ['2027-04-12', '2027-04-19'],
            Slot::query()->where('room', '1')->orderBy('starts_at')->pluck('starts_at')->map(fn ($at) => $at->toDateString())->all(),
        );
    }

    public function test_stop_series_unpublishes_future_empty_slots_only(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $student = $this->studentUser('Соколов Илья');

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => '27.10.2026',
            'start_time' => '15:00',
            'end_time' => '15:45',
            'building' => 'А',
            'room' => '77',
            'repeat_weekly' => '1',
            'repeat_until' => '17.11.2026',
        ])->assertRedirect(route('teacher.slots.index'));

        $slots = Slot::query()->where('room', '77')->orderBy('starts_at')->get();
        $this->assertCount(4, $slots);

        $past = $slots[0];
        $booked = $slots[1];
        $started = $slots[2];
        $future = $slots[3];

        $past->update([
            'starts_at' => Carbon::parse('2026-10-24 15:00:00'),
            'ends_at' => Carbon::parse('2026-10-24 15:45:00'),
        ]);
        $started->update([
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addMinutes(40),
        ]);
        $this->actingAs($student)->post(route('bookings.store', $booked))->assertRedirect();

        $other = $this->futureSlot($teacher, '18:00', '18:45', 'Б', '3');

        $this->actingAs($teacher)
            ->get(route('teacher.slots.index'))
            ->assertOk()
            ->assertSee('Остановить повтор')
            ->assertSee(route('teacher.slots.unpublish.confirm', $future), false);

        $this->actingAs($teacher)
            ->post(route('teacher.slots.series.stop.store', $future))
            ->assertRedirect(route('teacher.slots.index'));

        $this->assertTrue($past->refresh()->is_published);
        $this->assertTrue($booked->refresh()->is_published);
        $this->assertSame(Booking::BOOKED, $booked->bookings()->first()->status);
        $this->assertTrue($started->refresh()->is_published);
        $this->assertFalse($future->refresh()->is_published);
        $this->assertTrue($other->refresh()->is_published);

        $this->actingAs($teacher)
            ->get(route('teacher.slots.index'))
            ->assertOk()
            ->assertDontSee('Остановить повтор');
    }

    public function test_lists_mark_a_published_slot_after_its_end(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $student = $this->studentUser('Соколов Илья');
        $student->studentProfile->favoriteTeachers()->attach($teacher->teacherProfile->id);

        $inProgress = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => now()->subMinutes(20),
            'ends_at' => now()->addMinutes(25),
            'building' => 'А',
            'room' => '501',
            'is_published' => true,
        ]);
        $inProgress->bookings()->create([
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::BOOKED,
        ]);

        $ended = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'building' => 'А',
            'room' => '401',
            'is_published' => true,
        ]);
        $endedBooking = $ended->bookings()->create([
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::BOOKED,
        ]);

        $empty = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHours(2),
            'building' => 'А',
            'room' => '402',
            'is_published' => true,
        ]);

        $hidden = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => now()->subHours(4),
            'ends_at' => now()->subHours(3),
            'building' => 'А',
            'room' => '403',
            'is_published' => false,
        ]);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.slots.index'))
            ->assertOk()
            ->assertSee('Показать завершённые')
            ->getContent();

        $this->assertNull($inProgress->refresh()->conducted_at);
        $this->assertSame(Booking::BOOKED, $inProgress->bookings()->first()->status);
        $this->assertStringContainsString('занят', $this->rowHtml($html, 'ауд. 501'));
        $this->assertStringNotContainsString('data-finished', $this->rowHtml($html, 'ауд. 501'));

        $this->assertTrue($ended->refresh()->conducted_at->equalTo($ended->ends_at));
        $this->assertSame(Booking::COMPLETED, $endedBooking->refresh()->status);
        $this->assertTrue($endedBooking->completed_at->equalTo($ended->ends_at));
        $this->assertTrue($empty->refresh()->conducted_at->equalTo($empty->ends_at));
        $this->assertSame(0, $empty->bookings()->count());
        $this->assertNull($hidden->refresh()->conducted_at);
        $this->assertStringContainsString('data-finished', $this->rowHtml($html, 'ауд. 402'));
        $this->assertStringContainsString('проведён', $this->rowHtml($html, 'ауд. 402'));

        $this->actingAs($teacher)
            ->post(route('teacher.slots.conduct.store', $inProgress))
            ->assertRedirect(route('teacher.slots.index'));

        $marked = $inProgress->refresh();
        $this->assertTrue($marked->conducted_at->equalTo(now()));
        $this->assertTrue($marked->conducted_at->greaterThan($marked->starts_at));
        $this->assertTrue($marked->conducted_at->lessThan($marked->ends_at));
        $this->assertSame(Booking::COMPLETED, $marked->bookings()->first()->status);

        $fromHome = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => now()->subMinutes(90),
            'ends_at' => now()->subMinutes(30),
            'building' => 'В',
            'room' => '404',
            'is_published' => true,
        ]);
        $fromHome->bookings()->create([
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::BOOKED,
        ]);

        $this->actingAs($student)->get(route('home'))->assertOk();
        $this->assertTrue($fromHome->refresh()->conducted_at->equalTo($fromHome->ends_at));
        $this->assertSame(Booking::COMPLETED, $fromHome->bookings()->first()->status);

        $fromBookings = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => now()->subMinutes(50),
            'ends_at' => now()->subMinutes(5),
            'building' => 'Д',
            'room' => '405',
            'is_published' => true,
        ]);
        $fromBookings->bookings()->create([
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::BOOKED,
        ]);

        $bookingsPage = $this->actingAs($student)
            ->get(route('bookings.index'))
            ->assertOk()
            ->getContent();

        $this->assertTrue($fromBookings->refresh()->conducted_at->equalTo($fromBookings->ends_at));
        $this->assertSame(Booking::COMPLETED, $fromBookings->bookings()->first()->status);
        $this->assertStringContainsString('data-finished', $this->rowHtml($bookingsPage, 'ауд. 405'));
    }

    public function test_roster_page_sorts_by_group_then_name(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $other = $this->teacherUser('Петров Н. И.');
        $slot = $this->futureSlot($teacher, '11:00', '11:45', 'Главный А', '314');
        $morozov = $this->studentUser('Морозов Кирилл', 'ПИ-22');
        $sokolov = $this->studentUser('Соколов Илья', 'ПИ-21');
        $belova = $this->studentUser('Белова Анна', 'ПИ-21');

        foreach ([$morozov, $sokolov, $belova] as $student) {
            $this->actingAs($student)->post(route('bookings.store', $slot))->assertRedirect();
        }

        $this->actingAs($teacher)
            ->get(route('teacher.slots.index'))
            ->assertOk()
            ->assertDontSee('<details class="people"', false)
            ->assertSee('>3 студента</a>', false)
            ->assertSee(route('slots.roster', $slot), false);

        $page = $this->actingAs($teacher)
            ->get(route('slots.roster', $slot))
            ->assertOk()
            ->assertSee($slot->dateLabel())
            ->assertSee($slot->timeRange())
            ->assertSee($slot->place())
            ->assertSee('Группа')
            ->assertSee('Отменить')
            ->assertDontSee('Снять с публикации')
            ->assertDontSee('Отметить проведённой')
            ->assertSeeInOrder([
                'ПИ-21',
                'Белова Анна',
                'ПИ-21',
                'Соколов Илья',
                'ПИ-22',
                'Морозов Кирилл',
            ])
            ->getContent();

        $this->assertStringContainsString(route('teacher.bookings.cancel', Booking::query()->where('student_profile_id', $belova->studentProfile->id)->firstOrFail()), $page);

        $this->actingAs($sokolov)
            ->get(route('slots.roster', $slot))
            ->assertOk()
            ->assertDontSee('Отменить')
            ->assertDontSee('Снять с публикации')
            ->assertDontSee('Остановить повтор')
            ->assertSeeInOrder([
                'Белова Анна',
                'Соколов Илья',
                'Морозов Кирилл',
            ]);

        $this->actingAs($sokolov)
            ->get(route('bookings.create', $slot))
            ->assertOk()
            ->assertSee('<details class="people"', false);

        $removed = $this->futureSlot($teacher, '16:00', '16:45', 'Б', '9');
        $removed->update(['is_published' => false]);

        $this->actingAs($sokolov)->get(route('slots.roster', $removed))->assertForbidden();
        $this->actingAs($other)->get(route('slots.roster', $slot))->assertForbidden();
    }

    private function rowHtml(string $html, string $marker): string
    {
        $pattern = '/<tr\b[^>]*>(?:(?!<\/tr>).)*'.preg_quote($marker, '/').'(?:(?!<\/tr>).)*<\/tr>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }
}
