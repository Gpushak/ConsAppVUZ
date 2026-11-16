<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use Tests\ConsultationTestCase;

class NearestConsultationTest extends ConsultationTestCase
{
    public function test_nearest_slot_is_the_soonest_future_one_for_the_chosen_favorite(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $ivanova = $this->teacherUser('Иванова А. С.');
        $smirnova = $this->teacherUser('Смирнова Е. В.');
        $petrov = $this->teacherUser('Петров Н. И.');
        $student->studentProfile->favoriteTeachers()->attach([
            $ivanova->teacherProfile->id,
            $smirnova->teacherProfile->id,
            $petrov->teacherProfile->id,
        ]);

        $this->slot($ivanova, '2026-10-20 08:00:00', '2026-10-20 08:45:00', '111', conducted: true);
        $this->slot($ivanova, '2026-10-26 08:00:00', '2026-10-26 08:45:00', '222', published: false);
        $soon = $this->slot($ivanova, '2026-10-27 10:00:00', '2026-10-27 10:45:00', '333');
        $later = $this->slot($ivanova, '2026-10-27 16:00:00', '2026-10-27 16:45:00', '444');
        $other = $this->slot($smirnova, '2026-10-26 09:00:00', '2026-10-26 09:45:00', '555');
        $this->slot($petrov, '2026-10-22 12:00:00', '2026-10-22 12:45:00', '666', conducted: true);

        $html = $this->actingAs($student)
            ->get(route('home', [
                'teacher' => $ivanova->teacherProfile->id,
                'month' => '2026-10',
                'from' => '2026-10-01',
                'to' => '2026-11-30',
            ]))
            ->assertOk()
            ->assertSee('Ближайшая консультация')
            ->assertSee('value="'.$ivanova->teacherProfile->id.'" selected', false)
            ->getContent();

        $nearest = $this->nearestHtml($html);
        $this->assertStringContainsString('data-nearest="'.$soon->id.'"', $nearest);
        $this->assertStringContainsString($soon->dateLabel(), $nearest);
        $this->assertStringContainsString($soon->timeRange(), $nearest);
        $this->assertStringContainsString('ауд. 333', $nearest);
        $this->assertStringContainsString('свободен', $nearest);
        $this->assertStringNotContainsString('ауд. 444', $nearest);
        $this->assertStringNotContainsString('ауд. 555', $nearest);
        $this->assertStringNotContainsString('ауд. 222', $nearest);
        $this->assertStringNotContainsString('ауд. 111', $nearest);
        $this->assertStringNotContainsString('ауд. 666', $nearest);
        $this->assertNotSame($soon->id, $later->id);
        $this->assertTrue($other->starts_at->lessThan($soon->starts_at));

        $none = $this->actingAs($student)
            ->get(route('home', [
                'teacher' => $petrov->teacherProfile->id,
                'month' => '2026-10',
            ]))
            ->assertOk()
            ->assertSee('Ближайших консультаций нет.')
            ->assertDontSee('data-nearest', false)
            ->getContent();

        $this->assertStringNotContainsString('<section class="nearest"', $none);
    }

    public function test_calendar_marks_a_day_with_a_slot_and_leaves_an_empty_day_unmarked(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $ivanova = $this->teacherUser('Иванова А. С.');
        $smirnova = $this->teacherUser('Смирнова Е. В.');
        $petrov = $this->teacherUser('Петров Н. И.');
        $student->studentProfile->favoriteTeachers()->attach([
            $ivanova->teacherProfile->id,
            $smirnova->teacherProfile->id,
        ]);

        $this->slot($ivanova, '2026-10-27 10:00:00', '2026-10-27 10:45:00', '333');
        $this->slot($ivanova, '2026-10-28 23:30:00', '2026-10-29 00:15:00', '888');
        $this->slot($ivanova, '2026-10-24 09:00:00', '2026-10-24 09:45:00', '222', published: false);
        $this->slot($smirnova, '2026-10-21 12:00:00', '2026-10-21 12:45:00', '555');
        $this->slot($petrov, '2026-10-22 12:00:00', '2026-10-22 12:45:00', '666');

        $query = [
            'teacher' => $ivanova->teacherProfile->id,
            'month' => '2026-10',
            'from' => '2026-10-26',
            'to' => '2026-11-01',
        ];

        $html = $this->actingAs($student)
            ->get(route('home', $query))
            ->assertOk()
            ->assertSee('Октябрь 2026')
            ->getContent();

        $this->assertStringContainsString('marked', $this->calendarCell($html, '2026-10-27'));
        $this->assertStringContainsString('marked', $this->calendarCell($html, '2026-10-29'));
        $this->assertStringNotContainsString('marked', $this->calendarCell($html, '2026-10-28'));
        $this->assertStringNotContainsString('marked', $this->calendarCell($html, '2026-10-21'));
        $this->assertStringNotContainsString('marked', $this->calendarCell($html, '2026-10-22'));
        $this->assertStringNotContainsString('marked', $this->calendarCell($html, '2026-10-23'));
        $this->assertStringNotContainsString('marked', $this->calendarCell($html, '2026-10-24'));
        $this->assertStringContainsString('month=2026-09', $html);
        $this->assertStringContainsString('month=2026-11', $html);
        $this->assertStringContainsString('teacher='.$ivanova->teacherProfile->id, $html);
        $this->assertStringContainsString('from=2026-10-26', $html);
        $this->assertStringContainsString('to=2026-11-01', $html);

        $all = $this->actingAs($student)
            ->get(route('home', ['month' => '2026-10']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('marked', $this->calendarCell($all, '2026-10-21'));
        $this->assertStringContainsString('marked', $this->calendarCell($all, '2026-10-27'));
        $this->assertStringNotContainsString('marked', $this->calendarCell($all, '2026-10-22'));
        $this->assertStringNotContainsString('marked', $this->calendarCell($all, '2026-10-23'));
        $this->assertStringNotContainsString('teacher=', $this->monthNav($all));

        $may = $this->actingAs($student)
            ->get(route('home', [
                'teacher' => $ivanova->teacherProfile->id,
                'month' => '2027-05',
            ]))
            ->assertOk()
            ->assertSee('Май 2027')
            ->assertSee('month=2027-04', false)
            ->assertSee('month=2027-06', false)
            ->getContent();

        $this->assertStringNotContainsString('data-date="2026-10-27"', $may);
    }

    public function test_finished_slots_do_not_appear_on_the_home_week(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $teacher = $this->teacherUser('Иванова А. С.');
        $student->studentProfile->favoriteTeachers()->attach($teacher->teacherProfile->id);

        $done = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => '2026-10-20 10:00:00',
            'ends_at' => '2026-10-20 10:45:00',
            'building' => 'Главный А',
            'room' => '777',
            'comment' => 'архивная консультация',
            'is_published' => true,
            'conducted_at' => '2026-10-20 10:45:00',
        ]);
        Booking::query()->create([
            'slot_id' => $done->id,
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::COMPLETED,
            'completed_at' => '2026-10-20 10:45:00',
        ]);
        $this->slot($teacher, '2026-10-28 11:00:00', '2026-10-28 11:45:00', '314');

        $home = $this->actingAs($student)
            ->get(route('home', $this->listedSpan()))
            ->assertOk()
            ->assertDontSee('Показать завершённые')
            ->assertDontSee('Скрыть завершённые')
            ->assertDontSee('архивная консультация')
            ->assertDontSee('data-finished', false)
            ->assertDontSee('data-only-finished', false)
            ->getContent();

        $empty = $this->dayHtml($home, '20.10.2026');
        $this->assertStringContainsString('нет слотов', $empty);
        $this->assertStringNotContainsString('ауд. 777', $empty);
        $this->assertStringNotContainsString('data-slot-card', $empty);

        $filled = $this->dayHtml($home, '28.10.2026');
        $this->assertStringContainsString('ауд. 314', $filled);

        $this->actingAs($student)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('Показать завершённые')
            ->assertSee('архивная консультация')
            ->assertSee('ауд. 777');
    }

    private function slot(
        User $teacher,
        string $start,
        string $end,
        string $room,
        bool $published = true,
        bool $conducted = false,
    ): Slot {
        return Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'building' => 'Главный А',
            'room' => $room,
            'is_published' => $published,
            'conducted_at' => $conducted ? $end : null,
        ]);
    }

    private function nearestHtml(string $html): string
    {
        $this->assertSame(
            1,
            preg_match('/<section class="nearest" data-nearest="\d+">.*?<\/section>/s', $html, $match),
        );

        return $match[0];
    }

    private function calendarCell(string $html, string $date): string
    {
        $pattern = '/<td\b[^>]*\bdata-date="'.preg_quote($date, '/').'"[^>]*>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }

    private function monthNav(string $html): string
    {
        $this->assertSame(1, preg_match('/<div class="cal-nav">.*?<\/div>/s', $html, $match));

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
