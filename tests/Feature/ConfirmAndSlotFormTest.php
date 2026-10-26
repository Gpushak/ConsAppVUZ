<?php

namespace Tests\Feature;

use App\Models\Slot;
use Tests\ConsultationTestCase;

class ConfirmAndSlotFormTest extends ConsultationTestCase
{
    public function test_unpublish_confirm_page_does_not_change_the_slot_until_post(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $other = $this->teacherUser('Петров Н. И.');
        $student = $this->studentUser('Соколов Илья');
        $slot = $this->futureSlot($teacher, '10:00', '10:45', 'А', '101');
        $booked = $this->futureSlot($teacher, '12:00', '12:45', 'Б', '202');
        $this->actingAs($student)->post(route('bookings.store', $booked))->assertRedirect();

        $this->actingAs($teacher)
            ->get(route('teacher.slots.index'))
            ->assertOk()
            ->assertSee(route('teacher.slots.unpublish.confirm', $slot), false)
            ->assertDontSee('action="'.route('teacher.slots.unpublish', $slot).'"', false);

        $this->actingAs($teacher)
            ->get(route('teacher.slots.unpublish.confirm', $slot))
            ->assertOk()
            ->assertSee('Снять с публикации')
            ->assertSee('К моим слотам');
        $this->assertTrue($slot->refresh()->is_published);

        $this->actingAs($other)
            ->get(route('teacher.slots.unpublish.confirm', $slot))
            ->assertForbidden();
        $this->actingAs($other)
            ->post(route('teacher.slots.unpublish', $slot))
            ->assertForbidden();
        $this->assertTrue($slot->refresh()->is_published);

        $this->actingAs($teacher)
            ->get(route('teacher.slots.unpublish.confirm', $booked))
            ->assertForbidden();
        $this->actingAs($teacher)
            ->post(route('teacher.slots.unpublish', $booked))
            ->assertForbidden();
        $this->assertTrue($booked->refresh()->is_published);

        $this->actingAs($teacher)
            ->post(route('teacher.slots.unpublish', $slot))
            ->assertRedirect(route('teacher.slots.index'));
        $this->assertFalse($slot->refresh()->is_published);
    }

    public function test_block_confirm_page_does_not_block_until_post(): void
    {
        $admin = $this->adminUser();
        $student = $this->studentUser('Соколов Илья');

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(route('admin.users.block.confirm', $student), false)
            ->assertDontSee('action="'.route('admin.users.block', $student).'"', false);

        $this->actingAs($admin)
            ->get(route('admin.users.block.confirm', $student))
            ->assertOk()
            ->assertSee('Заблокировать')
            ->assertSee('К пользователям');
        $this->assertTrue($student->refresh()->is_active);

        $this->actingAs($student)
            ->get(route('admin.users.block.confirm', $student))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.users.block', $student))
            ->assertRedirect(route('admin.users.index'));
        $this->assertFalse($student->refresh()->is_active);
    }

    public function test_new_slot_form_starts_empty_with_date_and_time_controls(): void
    {
        $teacher = $this->teacherUser();
        $html = $this->actingAs($teacher)
            ->get(route('teacher.slots.create'))
            ->assertOk()
            ->assertSee('type="date"', false)
            ->assertSee('type="time"', false)
            ->assertSee('step="300"', false)
            ->assertSee('placeholder="220"', false)
            ->assertSee('placeholder="принести черновик курсовой"', false)
            ->assertSee('placeholder="https://"', false)
            ->assertSee('Выберите корпус')
            ->getContent();

        $this->assertSame(2, substr_count($html, 'step="300"'));
        $this->assertStringNotContainsString('inputmode="numeric"', $html);
        $this->assertStringNotContainsString('value="16:00"', $html);
        $this->assertStringNotContainsString('value="16:45"', $html);
        $this->assertStringNotContainsString('value="220"', $html);
        $this->assertStringNotContainsString('>принести черновик курсовой<', $html);
        $this->assertStringNotContainsString('30.10.2026', $html);
        $this->assertDoesNotMatchRegularExpression('/value="Главный Б"[^>]*selected|selected[^>]*value="Главный Б"/', $html);
    }

    public function test_date_and_time_still_validate_for_both_formats(): void
    {
        $teacher = $this->teacherUser();
        $day = now()->addDays(4)->toDateString();

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => $day,
            'start_time' => '16:00:00',
            'end_time' => '16:45:00',
            'building' => 'А',
            'room' => '101',
        ])->assertRedirect(route('teacher.slots.index'));

        $slot = Slot::query()->firstOrFail();
        $this->assertSame('16:00', $slot->starts_at->format('H:i'));
        $this->assertSame('16:45', $slot->ends_at->format('H:i'));

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'online',
            'date' => '31.02.2026',
            'start_time' => '18:00',
            'end_time' => '18:45',
            'meeting_url' => 'https://meet.example.com/x',
        ])->assertSessionHasErrors('date');

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'online',
            'date' => $day,
            'start_time' => '1600',
            'end_time' => '18:45',
            'meeting_url' => 'https://meet.example.com/x',
        ])->assertSessionHasErrors('start_time');

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'online',
            'date' => $day,
            'start_time' => '18:00',
            'end_time' => '18:45',
            'meeting_url' => '',
        ])->assertSessionHasErrors('meeting_url');

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => $day,
            'start_time' => '18:00',
            'end_time' => '18:45',
            'building' => 'Главный Б',
            'room' => '',
        ])->assertSessionHasErrors('room');

        $this->assertSame(1, Slot::query()->count());
    }
}
