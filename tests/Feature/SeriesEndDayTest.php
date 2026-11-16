<?php

namespace Tests\Feature;

use App\Models\Slot;
use Tests\ConsultationTestCase;

class SeriesEndDayTest extends ConsultationTestCase
{
    public function test_tuesday_start_allows_the_next_tuesday_and_rejects_a_wednesday(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');

        $this->actingAs($teacher)->post(route('teacher.slots.store'), $this->payload('201', '11:00', '11:45', [
            'repeat' => '1',
            'repeat_until' => '03.11.2026',
        ]))->assertRedirect(route('teacher.slots.index'));

        $this->assertSame(['2026-10-27', '2026-11-03'], $this->dates('201'));

        $this->actingAs($teacher)->post(route('teacher.slots.store'), $this->payload('202', '11:00', '11:45', [
            'repeat' => '1',
            'repeat_until' => '28.10.2026',
        ]))->assertSessionHasErrors([
            'repeat_until' => 'Дата окончания — тот же день недели, что и дата слота: вторник.',
        ]);
        $this->assertSame([], $this->dates('202'));
    }

    public function test_biweekly_end_allows_fourteen_days_and_rejects_seven(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');

        $this->actingAs($teacher)->post(route('teacher.slots.store'), $this->payload('203', '12:00', '12:45', [
            'repeat' => '2',
            'repeat_until' => '10.11.2026',
        ]))->assertRedirect(route('teacher.slots.index'));

        $this->assertSame(['2026-10-27', '2026-11-10'], $this->dates('203'));

        $this->actingAs($teacher)->post(route('teacher.slots.store'), $this->payload('204', '13:00', '13:45', [
            'repeat' => '2',
            'repeat_until' => '03.11.2026',
        ]))->assertSessionHasErrors([
            'repeat_until' => 'Дата окончания — через 14, 28 и далее дней от даты слота. День через 7 дней не подходит.',
        ]);
        $this->assertSame([], $this->dates('204'));
    }

    public function test_end_date_without_repeat_stays_unused(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');

        $this->actingAs($teacher)->post(route('teacher.slots.store'), $this->payload('205', '14:00', '14:45', [
            'repeat' => 'none',
            'repeat_until' => '28.10.2026',
        ]))->assertRedirect(route('teacher.slots.index'));

        $once = Slot::query()->where('room', '205')->get();
        $this->assertCount(1, $once);
        $this->assertNull($once->first()->series_id);
        $this->assertSame('2026-10-27', $once->first()->starts_at->toDateString());
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payload(string $room, string $start, string $end, array $overrides): array
    {
        return array_merge([
            'format' => 'in_person',
            'date' => '27.10.2026',
            'start_time' => $start,
            'end_time' => $end,
            'building' => 'А',
            'room' => $room,
        ], $overrides);
    }

    /**
     * @return list<string>
     */
    private function dates(string $room): array
    {
        return Slot::query()->where('room', $room)->orderBy('starts_at')->get()
            ->map(fn (Slot $slot) => $slot->starts_at->toDateString())
            ->all();
    }
}
