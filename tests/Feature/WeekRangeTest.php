<?php

namespace Tests\Feature;

use App\Models\Slot;
use App\Models\User;
use App\Support\DateSpan;
use Illuminate\Support\Carbon;
use Tests\ConsultationTestCase;

class WeekRangeTest extends ConsultationTestCase
{
    public function test_default_week_is_monday_to_sunday_in_yekaterinburg(): void
    {
        [$teacher, $student] = $this->pair();

        foreach ($this->pages($teacher, $student) as [$user, $name]) {
            $this->actingAs($user)
                ->get(route($name))
                ->assertOk()
                ->assertSee('value="2026-10-19"', false)
                ->assertSee('value="2026-10-25"', false)
                ->assertSee('Показать')
                ->assertSee('Предыдущая неделя')
                ->assertSee('Следующая неделя')
                ->assertSee('from=2026-10-12', false)
                ->assertSee('to=2026-10-18', false)
                ->assertSee('from=2026-10-26', false)
                ->assertSee('to=2026-11-01', false);
        }

        Carbon::setTestNow(Carbon::parse('2026-10-25 23:30:00', 'Europe/Moscow'));
        $this->slotOn($teacher, '2026-10-25', '251', '21:00', '21:45');
        $this->slotOn($teacher, '2026-10-26', '260', '00:30', '01:15');
        $this->slotOn($teacher, '2026-11-02', '202');

        foreach ($this->pages($teacher, $student) as [$user, $name]) {
            $this->actingAs($user)
                ->get(route($name))
                ->assertOk()
                ->assertSee('value="2026-10-26"', false)
                ->assertSee('value="2026-11-01"', false)
                ->assertSee('ауд. 260')
                ->assertDontSee('ауд. 251')
                ->assertDontSee('ауд. 202');
        }
    }

    public function test_slot_outside_the_range_is_absent_and_an_empty_day_says_so(): void
    {
        [$teacher, $student] = $this->pair();
        $this->slotOn($teacher, '2026-10-20', '201');
        $this->slotOn($teacher, '2026-10-24', '241');

        $query = ['from' => '2026-10-22', 'to' => '2026-10-25'];

        foreach ($this->pages($teacher, $student) as [$user, $name]) {
            $html = $this->actingAs($user)
                ->get(route($name, $query))
                ->assertOk()
                ->assertSee('value="2026-10-22"', false)
                ->assertSee('value="2026-10-25"', false)
                ->assertSee('ауд. 241')
                ->assertDontSee('ауд. 201')
                ->assertDontSee(DateSpan::ORDER_ERROR)
                ->getContent();

            $empty = $this->dayHtml($html, '23.10.2026');
            $this->assertStringContainsString('нет слотов', $empty);
            $filled = $this->dayHtml($html, '24.10.2026');
            $this->assertStringContainsString('ауд. 241', $filled);
            $this->assertStringNotContainsString('нет слотов', $filled);
        }
    }

    public function test_end_before_start_shows_an_error_and_does_not_hide_slots(): void
    {
        [$teacher, $student] = $this->pair();
        $this->slotOn($teacher, '2026-11-02', '555');

        foreach ($this->pages($teacher, $student) as [$user, $name]) {
            $this->actingAs($user)
                ->get(route($name, ['from' => '2026-11-10', 'to' => '2026-11-01']))
                ->assertOk()
                ->assertSee(DateSpan::ORDER_ERROR)
                ->assertSee('value="2026-11-10"', false)
                ->assertSee('value="2026-11-01"', false)
                ->assertSee('ауд. 555');
        }
    }

    public function test_previous_and_next_week_shift_both_dates_in_the_query(): void
    {
        [$teacher, $student] = $this->pair();
        $query = ['from' => '2026-05-24', 'to' => '2026-05-30'];

        foreach ($this->pages($teacher, $student) as [$user, $name]) {
            $html = $this->actingAs($user)
                ->get(route($name, $query))
                ->assertOk()
                ->assertSee('value="2026-05-24"', false)
                ->assertSee('value="2026-05-30"', false)
                ->getContent();

            $this->assertStringContainsString('from=2026-05-17', $html);
            $this->assertStringContainsString('to=2026-05-23', $html);
            $this->assertStringContainsString('from=2026-05-31', $html);
            $this->assertStringContainsString('to=2026-06-06', $html);
        }

        $partial = $this->actingAs($teacher)
            ->get(route('teacher.slots.index', ['from' => '2026-10-21', 'to' => '2026-10-23']))
            ->assertOk()
            ->assertSee('value="2026-10-21"', false)
            ->assertSee('value="2026-10-23"', false)
            ->getContent();

        $this->assertStringContainsString('from=2026-10-14', $partial);
        $this->assertStringContainsString('to=2026-10-16', $partial);
        $this->assertStringContainsString('from=2026-10-28', $partial);
        $this->assertStringContainsString('to=2026-10-30', $partial);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function pair(): array
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $student = $this->studentUser('Соколов Илья');
        $student->studentProfile->favoriteTeachers()->attach($teacher->teacherProfile->id);

        return [$teacher, $student];
    }

    /**
     * @return list<array{0: User, 1: string}>
     */
    private function pages(User $teacher, User $student): array
    {
        return [
            [$teacher, 'teacher.slots.index'],
            [$student, 'home'],
        ];
    }

    private function slotOn(User $teacher, string $day, string $room, string $start = '10:00', string $end = '10:45'): Slot
    {
        return Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => $day.' '.$start.':00',
            'ends_at' => $day.' '.$end.':00',
            'building' => 'Главный А',
            'room' => $room,
            'is_published' => true,
        ]);
    }

    private function dayHtml(string $html, string $iso): string
    {
        $pattern = '/<section class="day"[^>]*>(?:(?!<\/section>).)*'.preg_quote($iso, '/').'(?:(?!<\/section>).)*<\/section>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }
}
