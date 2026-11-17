<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use App\Support\DateSpan;
use Tests\ConsultationTestCase;

class BiweeklySeriesTest extends ConsultationTestCase
{
    public function test_create_form_offers_no_repeat_every_week_and_every_two_weeks(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $day = now()->addDays(4)->toDateString();

        $page = $this->actingAs($teacher)
            ->get(route('teacher.slots.create'))
            ->assertOk()
            ->assertSee('Не повторять')
            ->assertSee('Каждую неделю')
            ->assertSee('Каждые 2 недели')
            ->assertSee('Последний день повтора')
            ->assertSee('Учитывается, только если выбран повтор: «Каждую неделю» или «Каждые 2 недели». Не позже 30.06.2027.')
            ->getContent();

        $this->assertMatchesRegularExpression('/id="repeat_none"[^>]*\bchecked\b/', $page);
        $this->assertDoesNotMatchRegularExpression('/id="repeat_week"[^>]*\bchecked\b/', $page);
        $this->assertDoesNotMatchRegularExpression('/id="repeat_biweek"[^>]*\bchecked\b/', $page);

        $kept = $this->followingRedirects()
            ->actingAs($teacher)
            ->from(route('teacher.slots.create'))
            ->post(route('teacher.slots.store'), [
                'format' => 'in_person',
                'date' => $day,
                'start_time' => '15:00',
                'end_time' => '15:45',
                'building' => 'Б',
                'room' => '8',
                'repeat' => '2',
                'repeat_until' => now()->addDay()->format('d.m.Y'),
            ])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/id="repeat_biweek"[^>]*\bchecked\b/', $kept);
        $this->assertMatchesRegularExpression(
            '/id="repeat_until"[^>]*>\s*<p class="error" id="repeat_until-error">Дата окончания не раньше даты слота\.<\/p>/',
            $kept,
        );
        $this->assertStringContainsString('Последний день серии, не позже 30.06.2027.', $kept);
        $this->assertSame(0, Slot::query()->count());
    }

    public function test_biweekly_series_steps_fourteen_days_and_weekly_series_stays_on_seven(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');

        $this->actingAs($teacher)->post(route('teacher.slots.store'), $this->payload([
            'room' => '14',
            'repeat' => '2',
            'repeat_until' => '24.11.2026',
        ]))->assertRedirect(route('teacher.slots.index'))
            ->assertSessionHas('status', 'Опубликовано слотов: 3.');

        $biweekly = Slot::query()->where('room', '14')->orderBy('starts_at')->get();
        $this->assertSame(
            ['2026-10-27', '2026-11-10', '2026-11-24'],
            $biweekly->map(fn (Slot $slot) => $this->localDate($slot))->all(),
        );
        $this->assertNull(Slot::query()->where('room', '14')->whereDate('starts_at', '2026-11-03')->first());
        $this->assertNull(Slot::query()->where('room', '14')->whereDate('starts_at', '2026-11-17')->first());
        $this->assertCount(1, $biweekly->pluck('series_id')->unique());
        $this->assertNotNull($biweekly->first()->series_id);
        $this->assertTrue($biweekly->every(fn (Slot $slot) => $slot->series_weeks === 2));
        $this->assertTrue($biweekly->every(fn (Slot $slot) => $slot->seriesStepLabel() === 'каждые 2 недели'));
        $this->assertTrue($biweekly->every(fn (Slot $slot) => $slot->starts_at->format('H:i') === '11:00'));
        $this->assertTrue($biweekly->every(fn (Slot $slot) => $slot->ends_at->format('H:i') === '11:45'));
        $this->assertTrue($biweekly[0]->starts_at->copy()->addDays(14)->equalTo($biweekly[1]->starts_at));
        $this->assertTrue($biweekly[1]->starts_at->copy()->addDays(14)->equalTo($biweekly[2]->starts_at));

        $this->actingAs($teacher)->post(route('teacher.slots.store'), $this->payload([
            'room' => '7',
            'start_time' => '12:00',
            'end_time' => '12:45',
            'repeat' => '1',
            'repeat_until' => '10.11.2026',
        ]))->assertRedirect(route('teacher.slots.index'))
            ->assertSessionHas('status', 'Опубликовано слотов: 3.');

        $weekly = Slot::query()->where('room', '7')->orderBy('starts_at')->get();
        $this->assertSame(
            ['2026-10-27', '2026-11-03', '2026-11-10'],
            $weekly->map(fn (Slot $slot) => $this->localDate($slot))->all(),
        );
        $this->assertTrue($weekly->every(fn (Slot $slot) => $slot->series_weeks === 1));
        $this->assertTrue($weekly->every(fn (Slot $slot) => $slot->seriesStepLabel() === 'каждую неделю'));
        $this->assertTrue($weekly->every(fn (Slot $slot) => $slot->starts_at->format('H:i') === '12:00'));
        $this->assertTrue($weekly[0]->starts_at->copy()->addDays(7)->equalTo($weekly[1]->starts_at));
        $this->assertTrue($weekly[1]->starts_at->copy()->addDays(7)->equalTo($weekly[2]->starts_at));
        $this->assertNotSame($biweekly->first()->series_id, $weekly->first()->series_id);

        Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => '2026-11-10 13:00:00',
            'ends_at' => '2026-11-10 13:30:00',
            'building' => 'А',
            'room' => '5',
            'is_published' => true,
        ]);

        $this->actingAs($teacher)->post(route('teacher.slots.store'), $this->payload([
            'room' => '15',
            'start_time' => '13:00',
            'end_time' => '13:45',
            'repeat' => '2',
            'repeat_until' => '24.11.2026',
        ]))->assertRedirect(route('teacher.slots.index'))
            ->assertSessionHas('status', 'Опубликовано слотов: 2. Дни с пересечением пропущены.');

        $skipped = Slot::query()->where('room', '15')->orderBy('starts_at')->get();
        $this->assertSame(
            ['2026-10-27', '2026-11-24'],
            $skipped->map(fn (Slot $slot) => $this->localDate($slot))->all(),
        );
        $this->assertTrue($skipped[0]->starts_at->copy()->addDays(28)->equalTo($skipped[1]->starts_at));
        $this->assertSame(2, $skipped->first()->series_weeks);

        $this->actingAs($teacher)->post(route('teacher.slots.store'), $this->payload([
            'date' => '28.10.2026',
            'room' => '1',
        ]))->assertRedirect(route('teacher.slots.index'));

        $once = Slot::query()->where('room', '1')->firstOrFail();
        $this->assertNull($once->series_id);
        $this->assertNull($once->series_weeks);
        $this->assertNull($once->seriesStepLabel());

        $this->actingAs($teacher)->post(route('teacher.slots.store'), $this->payload([
            'date' => '16.06.2027',
            'room' => '19',
            'repeat' => '2',
            'repeat_until' => '14.07.2027',
        ]))->assertSessionHasErrors([
            'repeat_until' => 'Дата окончания не позже 30.06.2027.',
        ]);
        $this->assertNull(Slot::query()->where('room', '19')->first());

        $this->actingAs($teacher)->post(route('teacher.slots.store'), $this->payload([
            'date' => '16.06.2027',
            'room' => '19',
            'repeat' => '2',
            'repeat_until' => '30.06.2027',
        ]))->assertRedirect(route('teacher.slots.index'));

        $bounded = Slot::query()->where('room', '19')->orderBy('starts_at')->get();
        $this->assertSame(
            ['2027-06-16', '2027-06-30'],
            $bounded->map(fn (Slot $slot) => $this->localDate($slot))->all(),
        );
        $this->assertNull(Slot::query()->where('room', '19')->whereDate('starts_at', '2027-06-23')->first());
        $this->assertTrue($bounded->every(fn (Slot $slot) => $slot->series_weeks === 2));
    }

    public function test_stop_and_apply_treat_a_biweekly_series_like_a_weekly_one(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $student = $this->studentUser('Соколов Илья');

        $this->actingAs($teacher)->post(route('teacher.slots.store'), $this->payload([
            'room' => '77',
            'repeat' => '2',
            'repeat_until' => '08.12.2026',
        ]))->assertRedirect(route('teacher.slots.index'));

        $series = Slot::query()->where('room', '77')->orderBy('starts_at')->get();
        $this->assertSame(
            ['2026-10-27', '2026-11-10', '2026-11-24', '2026-12-08'],
            $series->map(fn (Slot $slot) => $this->localDate($slot))->all(),
        );

        $anchor = $series[0];
        $booked = $series[1];
        $free = $series[2];
        $conducted = $series[3];
        $conducted->update(['conducted_at' => now()]);
        $this->actingAs($student)->post(route('bookings.store', $booked))->assertRedirect();

        $html = $this->actingAs($teacher)
            ->get(route('teacher.slots.index', $this->listedSpan()))
            ->assertOk()
            ->assertDontSee('Показать завершённые')
            ->assertDontSee('Скрыть завершённые')
            ->getContent();

        $nearest = $this->rowHtml($html, '27.10.2026');
        $this->assertStringContainsString('каждые 2 недели', $nearest);
        $this->assertStringContainsString('впереди ещё 2 дня', $nearest);
        $this->assertStringNotContainsString('каждую неделю', $nearest);

        $middle = $this->rowHtml($html, '10.11.2026');
        $this->assertStringContainsString('каждые 2 недели', $middle);
        $this->assertStringNotContainsString('впереди ещё', $middle);

        $later = $this->rowHtml($html, '24.11.2026');
        $this->assertStringContainsString('каждые 2 недели', $later);
        $this->assertStringNotContainsString('впереди ещё', $later);
        $this->assertSame(1, substr_count($html, 'впереди ещё'));

        $gap = $this->dayHtml($html, '03.11.2026');
        $this->assertStringContainsString('нет слотов', $gap);
        $this->assertStringNotContainsString('data-slot-card', $gap);

        $this->actingAs($teacher)
            ->post(route('teacher.slots.update', $anchor), [
                'format' => 'in_person',
                'date' => '2026-10-27',
                'start_time' => '14:00',
                'end_time' => '14:45',
                'building' => 'Б',
                'room' => '220',
                'comment' => 'новое место',
                'meeting_url' => 'https://meet.example.com/biweek',
                'apply_to_series' => '1',
            ])
            ->assertRedirect(route('teacher.slots.index'))
            ->assertSessionHas('status', 'Слот изменён. Будут изменены ещё 2 дня серии.');

        $anchor->refresh();
        $this->assertSame('2026-10-27 14:00', $anchor->starts_at->format('Y-m-d H:i'));
        $this->assertSame('14:45', $anchor->ends_at->format('H:i'));
        $this->assertSame('220', $anchor->room);
        $this->assertSame('новое место', $anchor->comment);
        $this->assertSame('https://meet.example.com/biweek', $anchor->meeting_url);

        $booked->refresh();
        $this->assertSame('2026-11-10 11:00', $booked->starts_at->format('Y-m-d H:i'));
        $this->assertSame('11:45', $booked->ends_at->format('H:i'));
        $this->assertSame('220', $booked->room);
        $this->assertSame('новое место', $booked->comment);
        $this->assertSame('https://meet.example.com/biweek', $booked->meeting_url);
        $this->assertSame(Booking::BOOKED, $booked->bookings()->first()->status);

        $free->refresh();
        $this->assertSame('2026-11-24 14:00', $free->starts_at->format('Y-m-d H:i'));
        $this->assertSame('14:45', $free->ends_at->format('H:i'));
        $this->assertSame('220', $free->room);
        $this->assertSame('новое место', $free->comment);

        $conducted->refresh();
        $this->assertSame('2026-12-08 11:00', $conducted->starts_at->format('Y-m-d H:i'));
        $this->assertSame('77', $conducted->room);
        $this->assertNull($conducted->meeting_url);
        $this->assertNotNull($conducted->conducted_at);

        $this->actingAs($teacher)
            ->post(route('teacher.slots.series.stop.store', $anchor))
            ->assertRedirect(route('teacher.slots.index'))
            ->assertSessionHas('status', 'Повтор остановлен. Будущие пустые слоты сняты с публикации.');

        $this->assertFalse($anchor->refresh()->is_published);
        $this->assertTrue($booked->refresh()->is_published);
        $this->assertFalse($free->refresh()->is_published);
        $this->assertTrue($conducted->refresh()->is_published);
        $this->assertSame(Booking::BOOKED, $booked->bookings()->first()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'format' => 'in_person',
            'date' => '27.10.2026',
            'start_time' => '11:00',
            'end_time' => '11:45',
            'building' => 'А',
            'room' => '14',
            'comment' => 'черновик',
            'repeat' => 'none',
        ], $overrides);
    }

    private function localDate(Slot $slot): string
    {
        return $slot->starts_at->copy()->timezone(DateSpan::TIMEZONE)->toDateString();
    }

    private function rowHtml(string $html, string $marker): string
    {
        $pattern = '/<article\b[^>]*>(?:(?!<\/article>).)*'.preg_quote($marker, '/').'(?:(?!<\/article>).)*<\/article>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }

    private function dayHtml(string $html, string $iso): string
    {
        $pattern = '/<section class="day"[^>]*>(?:(?!<\/section>).)*'.preg_quote($iso, '/').'(?:(?!<\/section>).)*<\/section>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }
}
