<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use Tests\ConsultationTestCase;

class SiteImprovementTest extends ConsultationTestCase
{
    public function test_one_stop_button_per_series_on_the_nearest_future_slot(): void
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

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => '28.10.2026',
            'start_time' => '18:00',
            'end_time' => '18:45',
            'building' => 'Б',
            'room' => '88',
            'repeat_weekly' => '1',
            'repeat_until' => '11.11.2026',
        ])->assertRedirect(route('teacher.slots.index'));

        $series = Slot::query()->where('room', '77')->orderBy('starts_at')->get();
        $this->assertCount(4, $series);
        $nearest = $series->first();
        $this->actingAs($student)->post(route('bookings.store', $nearest))->assertRedirect();

        $html = $this->actingAs($teacher)
            ->get(route('teacher.slots.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($html, '>Остановить повтор</a>'));

        $nearestRow = $this->rowHtml($html, '27.10.2026');
        $this->assertStringContainsString('>Остановить повтор</a>', $nearestRow);
        $this->assertStringContainsString(route('teacher.slots.series.stop', $nearest), $nearestRow);
        $this->assertStringNotContainsString('еженедельно', $nearestRow);
        $this->assertStringNotContainsString(route('teacher.slots.unpublish.confirm', $nearest), $nearestRow);

        foreach ($series->skip(1) as $slot) {
            $row = $this->rowHtml($html, $slot->dateLabel());
            $this->assertStringContainsString('еженедельно', $row);
            $this->assertStringNotContainsString('Остановить повтор', $row);
            $this->assertStringContainsString(route('teacher.slots.unpublish.confirm', $slot), $row);
        }

        $other = Slot::query()->where('room', '88')->orderBy('starts_at')->firstOrFail();
        $otherRow = $this->rowHtml($html, '28.10.2026');
        $this->assertStringContainsString(route('teacher.slots.series.stop', $other), $otherRow);
        $this->assertSame(1, substr_count($otherRow, 'Остановить повтор'));
    }

    public function test_edit_locks_booked_time_and_rejects_past_and_overlap(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $otherTeacher = $this->teacherUser('Петров Н. И.');
        $student = $this->studentUser('Соколов Илья');
        $slot = $this->futureSlot($teacher, '10:00', '10:45', 'А', '101');

        $this->actingAs($teacher)->post(route('teacher.slots.update', $slot), $this->editPayload($slot, [
            'date' => now()->addDays(4)->toDateString(),
            'start_time' => '12:00',
            'end_time' => '12:45',
            'building' => 'Б',
            'room' => '202',
            'comment' => 'новый комментарий',
            'meeting_url' => 'https://meet.example.com/b',
        ]))->assertRedirect(route('teacher.slots.index'));

        $slot->refresh();
        $this->assertSame('2026-10-29 12:00', $slot->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-29 12:45', $slot->ends_at->format('Y-m-d H:i'));
        $this->assertSame('Б', $slot->building);
        $this->assertSame('202', $slot->room);
        $this->assertSame('новый комментарий', $slot->comment);
        $this->assertSame('https://meet.example.com/b', $slot->meeting_url);

        $this->actingAs($student)->post(route('bookings.store', $slot))->assertRedirect();

        $this->actingAs($teacher)
            ->get(route('teacher.slots.edit', $slot))
            ->assertOk()
            ->assertSee('Время нельзя изменить: на слот уже есть запись.')
            ->assertSee('Сохранить');

        $this->actingAs($teacher)->post(route('teacher.slots.update', $slot), $this->editPayload($slot, [
            'date' => now()->addDays(5)->toDateString(),
            'start_time' => '15:00',
            'end_time' => '15:45',
            'building' => 'В',
            'room' => '8',
            'comment' => 'место можно',
            'meeting_url' => 'https://meet.example.com/c',
        ]))->assertSessionHasErrors([
            'start_time' => 'Время нельзя изменить: на слот уже есть запись.',
        ]);

        $slot->refresh();
        $this->assertSame('2026-10-29 12:00', $slot->starts_at->format('Y-m-d H:i'));
        $this->assertSame('202', $slot->room);
        $this->assertSame('новый комментарий', $slot->comment);

        $this->actingAs($teacher)->post(route('teacher.slots.update', $slot), $this->editPayload($slot, [
            'building' => 'В',
            'room' => '8',
            'comment' => 'место можно',
            'meeting_url' => 'https://meet.example.com/c',
        ]))->assertRedirect(route('teacher.slots.index'));

        $slot->refresh();
        $this->assertSame('2026-10-29 12:00', $slot->starts_at->format('Y-m-d H:i'));
        $this->assertSame('12:45', $slot->ends_at->format('H:i'));
        $this->assertSame('В', $slot->building);
        $this->assertSame('8', $slot->room);
        $this->assertSame('место можно', $slot->comment);
        $this->assertSame('https://meet.example.com/c', $slot->meeting_url);

        $free = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => '2026-10-31 09:00:00',
            'ends_at' => '2026-10-31 09:45:00',
            'building' => 'А',
            'room' => '1',
            'is_published' => true,
        ]);
        Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => '2026-10-31 11:00:00',
            'ends_at' => '2026-10-31 11:45:00',
            'building' => 'А',
            'room' => '2',
            'is_published' => true,
        ]);

        $this->actingAs($teacher)->post(route('teacher.slots.update', $free), $this->editPayload($free, [
            'start_time' => '11:00',
            'end_time' => '11:45',
            'room' => '9',
        ]))->assertSessionHasErrors([
            'start_time' => 'Это время пересекается с другим вашим слотом.',
        ]);

        $this->assertSame('09:00', $free->refresh()->starts_at->format('H:i'));
        $this->assertSame('1', $free->room);

        $past = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => now()->subDay()->setTime(10, 0),
            'ends_at' => now()->subDay()->setTime(10, 45),
            'building' => 'А',
            'room' => '3',
            'is_published' => true,
        ]);

        $this->actingAs($teacher)->get(route('teacher.slots.edit', $past))->assertForbidden();
        $this->actingAs($teacher)->post(route('teacher.slots.update', $past), $this->editPayload($past, [
            'room' => '4',
        ]))->assertForbidden();
        $this->assertSame('3', $past->refresh()->room);

        $conducted = $this->futureSlot($teacher, '16:00', '16:45', 'Г', '7');
        $conducted->update(['conducted_at' => now()]);
        $this->actingAs($teacher)->post(route('teacher.slots.update', $conducted), $this->editPayload($conducted, [
            'room' => '70',
        ]))->assertForbidden();
        $this->assertSame('7', $conducted->refresh()->room);

        $this->actingAs($otherTeacher)->get(route('teacher.slots.edit', $slot))->assertForbidden();
        $this->actingAs($otherTeacher)->post(route('teacher.slots.update', $slot), $this->editPayload($slot, [
            'room' => '99',
        ]))->assertForbidden();
        $this->assertSame('8', $slot->refresh()->room);
    }

    public function test_nearest_active_booking_is_first_and_home_hides_other_names(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $other = $this->studentUser('Белова Анна');
        $teacher = $this->teacherUser('Иванова А. С.');
        $student->studentProfile->favoriteTeachers()->attach($teacher->teacherProfile->id);

        $near = $this->slotOn($teacher, 2, '501');
        $far = $this->slotOn($teacher, 10, '502');
        $cancelled = $this->slotOn($teacher, 1, '504');
        $completed = $this->slotOn($teacher, -3, '503');
        $completed->update(['conducted_at' => $completed->ends_at]);
        $open = $this->slotOn($teacher, 4, '505');

        foreach ([$near, $far] as $slot) {
            $slot->bookings()->create([
                'student_profile_id' => $student->studentProfile->id,
                'status' => Booking::BOOKED,
            ]);
        }

        $near->bookings()->create([
            'student_profile_id' => $other->studentProfile->id,
            'status' => Booking::BOOKED,
        ]);

        $cancelled->bookings()->create([
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::CANCELLED_BY_STUDENT,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $student->id,
        ]);

        $completed->bookings()->create([
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::COMPLETED,
            'completed_at' => $completed->ends_at,
        ]);

        $this->actingAs($student)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSeeInOrder(['ауд. 501', 'ауд. 502', 'ауд. 504', 'ауд. 503']);

        $home = $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Вы записаны')
            ->assertSee('Записаться')
            ->assertDontSee('Белова Анна')
            ->getContent();

        $this->assertStringContainsString('>Записаться</a>', $this->rowHtml($home, 'ауд. 505'));
        $this->assertStringContainsString('>Вы записаны</a>', $this->rowHtml($home, 'ауд. 501'));
        $this->assertStringNotContainsString('Белова Анна', $this->rowHtml($home, 'ауд. 501'));

        $this->actingAs($student)
            ->get(route('slots.roster', $near))
            ->assertOk()
            ->assertSee('Белова Анна');

        $this->assertTrue($open->is_published);
    }

    public function test_admin_updates_a_teacher_position_and_department(): void
    {
        $admin = $this->adminUser();
        $teacher = $this->teacherUser('Иванова А. С.');
        $student = $this->studentUser('Соколов Илья');

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Должность и кафедра')
            ->assertSee('Заблокировать')
            ->assertSee('Новый пароль')
            ->assertSee('доцент, программная инженерия');

        $this->actingAs($admin)
            ->post(route('admin.users.profile', $teacher), [
                'position' => 'профессор',
                'department' => 'информатика',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status', 'Должность и кафедра сохранены.');

        $teacher->refresh();
        $this->assertSame('профессор', $teacher->teacherProfile->position);
        $this->assertSame('информатика', $teacher->teacherProfile->department);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('профессор, информатика')
            ->assertSee('Заблокировать')
            ->assertSee('Новый пароль');

        $this->actingAs($admin)
            ->post(route('admin.users.profile', $teacher), [
                'position' => '   ',
                'department' => '',
            ])
            ->assertSessionHasErrors(['position', 'department']);

        $this->assertSame('профессор', $teacher->teacherProfile->refresh()->position);
        $this->assertSame('информатика', $teacher->teacherProfile->department);

        $this->actingAs($admin)
            ->post(route('admin.users.profile', $student), [
                'position' => 'профессор',
                'department' => 'информатика',
            ])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function editPayload(Slot $slot, array $overrides = []): array
    {
        return array_merge([
            'format' => 'in_person',
            'date' => $slot->starts_at->toDateString(),
            'start_time' => $slot->starts_at->format('H:i'),
            'end_time' => $slot->ends_at->format('H:i'),
            'building' => $slot->building,
            'room' => $slot->room,
            'comment' => $slot->comment,
            'meeting_url' => $slot->meeting_url,
        ], $overrides);
    }

    private function slotOn(User $teacher, int $offsetDays, string $room): Slot
    {
        $start = now()->startOfDay()->addDays($offsetDays)->setTime(10, 0);

        return Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->setTime(10, 45),
            'building' => 'Главный А',
            'room' => $room,
            'is_published' => true,
        ]);
    }

    private function rowHtml(string $html, string $marker): string
    {
        $pattern = '/<tr\b[^>]*>(?:(?!<\/tr>).)*'.preg_quote($marker, '/').'(?:(?!<\/tr>).)*<\/tr>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }
}
