<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\ConsultationTestCase;

class NextSlotPanelTest extends ConsultationTestCase
{
    public function test_panel_shows_the_soonest_future_slot_and_active_signup_count_outside_the_week(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $booked = $this->studentUser('Соколов Илья');
        $second = $this->studentUser('Белова Анна');
        $cancelled = $this->studentUser('Морозов Кирилл', 'ПИ-22');

        $this->makeSlot($teacher, '2026-10-20 10:00:00', '2026-10-20 10:45:00', '111', conducted: true);
        $this->makeSlot($teacher, '2026-11-03 09:00:00', '2026-11-03 09:45:00', '222', published: false);
        $inWeek = $this->makeSlot($teacher, '2026-10-25 08:00:00', '2026-10-25 09:45:00', '250');
        $soon = $this->makeSlot($teacher, '2026-11-02 10:00:00', '2026-11-02 10:45:00', '333');
        $later = $this->makeSlot($teacher, '2026-11-09 16:00:00', '2026-11-09 16:45:00', '444');
        Booking::query()->create([
            'slot_id' => $inWeek->id,
            'student_profile_id' => $booked->studentProfile->id,
            'status' => Booking::BOOKED,
        ]);

        foreach ([$booked, $second] as $student) {
            Booking::query()->create([
                'slot_id' => $soon->id,
                'student_profile_id' => $student->studentProfile->id,
                'status' => Booking::BOOKED,
            ]);
        }
        Booking::query()->create([
            'slot_id' => $soon->id,
            'student_profile_id' => $cancelled->studentProfile->id,
            'status' => Booking::CANCELLED_BY_STUDENT,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $cancelled->id,
        ]);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.slots.index', ['from' => '2026-10-19', 'to' => '2026-10-25']))
            ->assertOk()
            ->assertSee('value="2026-10-19"', false)
            ->assertSee('value="2026-10-25"', false)
            ->assertSee('<span>С</span>', false)
            ->assertSee('<span>По</span>', false)
            ->assertDontSee('Показать завершённые')
            ->getContent();

        $panel = $this->panelHtml($html);
        $this->assertLessThan(strpos($html, '<h1>Мои слоты</h1>'), strpos($html, 'class="next-slot"'));
        $this->assertLessThan(strpos($html, '<div class="week">'), strpos($html, 'data-next-slot="'.$soon->id.'"'));
        $this->assertStringContainsString('data-next-slot="'.$soon->id.'"', $panel);
        $this->assertStringContainsString($soon->dateLabel(), $panel);
        $this->assertStringContainsString($soon->timeRange(), $panel);
        $this->assertStringContainsString('Главный А, ауд. 333', $panel);
        $this->assertStringContainsString('свободен', $panel);
        $this->assertStringContainsString('>2 студента</a>', $panel);
        $this->assertStringContainsString(route('slots.roster', $soon), $panel);
        $this->assertStringNotContainsString('3 студента', $panel);
        $this->assertStringContainsString(route('teacher.slots.edit', $soon), $panel);
        $this->assertStringContainsString('>Изменить</a>', $panel);
        $this->assertStringNotContainsString('Остановить повтор', $panel);
        $this->assertStringNotContainsString('ауд. 444', $panel);
        $this->assertStringNotContainsString('ауд. 250', $panel);
        $this->assertStringNotContainsString('ауд. 222', $panel);
        $this->assertStringNotContainsString('ауд. 111', $panel);
        $this->assertNotSame($soon->id, $later->id);

        $week = strstr($html, '<div class="week">');
        $this->assertIsString($week);
        $this->assertStringNotContainsString('ауд. 333', $week);
        $this->assertStringNotContainsString('data-next-slot', $week);
        $this->assertStringContainsString('aria-expanded="false"', $week);
    }

    public function test_series_slot_panel_shows_edit_and_stop_repeat(): void
    {
        foreach ([1, 2] as $weeks) {
            $teacher = $this->teacherUser('Преподаватель '.$weeks);
            $seriesId = (string) Str::uuid();
            $step = $weeks === 1 ? 7 : 14;
            $soon = $this->makeSlot($teacher, '2026-11-02 10:00:00', '2026-11-02 10:45:00', '77'.$weeks, seriesId: $seriesId, seriesWeeks: $weeks);
            $laterStart = $soon->starts_at->copy()->addDays($step);
            $this->makeSlot(
                $teacher,
                $laterStart->toDateTimeString(),
                $laterStart->copy()->setTime(10, 45)->toDateTimeString(),
                '99'.$weeks,
                seriesId: $seriesId,
                seriesWeeks: $weeks,
            );

            $html = $this->actingAs($teacher)
                ->get(route('teacher.slots.index', ['from' => '2026-10-19', 'to' => '2026-10-25']))
                ->assertOk()
                ->getContent();

            $panel = $this->panelHtml($html);
            $this->assertStringContainsString('data-next-slot="'.$soon->id.'"', $panel);
            $this->assertStringContainsString('>Изменить</a>', $panel);
            $this->assertStringContainsString(route('teacher.slots.edit', $soon), $panel);
            $this->assertStringContainsString('>Остановить повтор</a>', $panel);
            $this->assertStringContainsString(route('teacher.slots.series.stop', $soon), $panel);
            $this->assertSame($weeks, $soon->refresh()->series_weeks);

            $week = strstr($html, '<div class="week">');
            $this->assertIsString($week);
            $this->assertStringNotContainsString('ауд. 77'.$weeks, $week);
            $this->assertStringNotContainsString('Остановить повтор', $week);
        }
    }

    public function test_teacher_with_no_future_slot_sees_the_empty_line(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $this->makeSlot($teacher, '2026-10-20 10:00:00', '2026-10-20 10:45:00', '111', conducted: true);
        $this->makeSlot($teacher, '2026-11-02 10:00:00', '2026-11-02 10:45:00', '222', published: false);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.slots.index', ['from' => '2026-11-02', 'to' => '2026-11-08']))
            ->assertOk()
            ->getContent();

        $panel = $this->panelHtml($html);
        $this->assertLessThan(strpos($html, '<h1>Мои слоты</h1>'), strpos($html, 'class="next-slot"'));
        $this->assertStringContainsString('Ближайших консультаций нет.', $panel);
        $this->assertStringNotContainsString('data-next-slot', $panel);
        $this->assertStringNotContainsString('ауд. 111', $panel);
        $this->assertStringNotContainsString('ауд. 222', $panel);
        $this->assertStringNotContainsString('Изменить', $panel);
        $this->assertStringNotContainsString('Остановить повтор', $panel);
    }

    private function panelHtml(string $html): string
    {
        $this->assertSame(1, preg_match('/<section class="next-slot\b.*?<\/section>/s', $html, $match));

        return $match[0];
    }

    private function makeSlot(
        User $teacher,
        string $start,
        string $end,
        string $room,
        bool $published = true,
        bool $conducted = false,
        ?string $seriesId = null,
        ?int $seriesWeeks = null,
    ): Slot {
        return Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'series_id' => $seriesId,
            'series_weeks' => $seriesWeeks,
            'starts_at' => $start,
            'ends_at' => $end,
            'building' => 'Главный А',
            'room' => $room,
            'is_published' => $published,
            'conducted_at' => $conducted ? $end : null,
        ]);
    }
}
