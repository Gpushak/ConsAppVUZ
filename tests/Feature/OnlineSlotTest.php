<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use Tests\ConsultationTestCase;

class OnlineSlotTest extends ConsultationTestCase
{
    public function test_online_slot_requires_a_link_and_stores_no_building_or_room(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');

        $this->publish($teacher, [
            'format' => 'online',
            'building' => 'Главный А',
            'room' => '220',
            'meeting_url' => 'https://meet.example.com/room',
            'comment' => 'подключиться за пять минут',
        ])->assertRedirect(route('teacher.slots.index'));

        $slot = Slot::query()->firstOrFail();
        $this->assertNull($slot->building);
        $this->assertNull($slot->room);
        $this->assertTrue($slot->isOnline());
        $this->assertSame('https://meet.example.com/room', $slot->meeting_url);

        $this->actingAs($teacher)
            ->get(route('teacher.slots.create'))
            ->assertOk()
            ->assertSee('Очно')
            ->assertSee('Онлайн')
            ->assertSee('name="format"', false);

        $this->actingAs($teacher)
            ->get(route('teacher.slots.index'))
            ->assertOk()
            ->assertSee('https://meet.example.com/room')
            ->assertDontSee('ауд.');
    }

    public function test_online_slot_without_a_link_is_rejected(): void
    {
        $teacher = $this->teacherUser();

        $this->publish($teacher, [
            'format' => 'online',
            'meeting_url' => '',
        ])->assertSessionHasErrors('meeting_url');

        $this->assertSame(0, Slot::query()->count());
    }

    public function test_online_slot_rejects_a_link_that_is_not_http_or_https(): void
    {
        $teacher = $this->teacherUser();

        foreach (['ftp://files.example.com/room', 'javascript:alert(1)', 'meet.example.com/room'] as $url) {
            $this->publish($teacher, [
                'format' => 'online',
                'meeting_url' => $url,
            ])->assertSessionHasErrors('meeting_url');
        }

        $this->assertSame(0, Slot::query()->count());
    }

    public function test_in_person_slot_still_rejects_a_missing_room(): void
    {
        $teacher = $this->teacherUser();

        $this->publish($teacher, [
            'format' => 'in_person',
            'building' => 'Главный Б',
            'room' => '',
            'meeting_url' => 'https://meet.example.com/extra',
        ])->assertSessionHasErrors('room');

        $this->assertSame(0, Slot::query()->count());
    }

    public function test_in_person_slot_keeps_an_optional_link(): void
    {
        $teacher = $this->teacherUser();
        $student = $this->studentUser('Соколов Илья');
        $student->studentProfile->favoriteTeachers()->attach($teacher->teacherProfile->id);

        $this->publish($teacher, [
            'format' => 'in_person',
            'building' => 'В',
            'room' => '105',
            'comment' => 'разбор лабораторной',
            'meeting_url' => 'https://meet.example.com/lab',
        ])->assertRedirect();

        $slot = Slot::query()->firstOrFail();
        $this->assertSame('В', $slot->building);
        $this->assertSame('105', $slot->room);
        $this->assertFalse($slot->isOnline());

        $this->actingAs($student)->post(route('bookings.store', $slot))->assertRedirect(route('bookings.index'));

        $this->actingAs($student)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('В, ауд. 105')
            ->assertSee('https://meet.example.com/lab')
            ->assertSee('разбор лабораторной');
    }

    public function test_student_home_and_bookings_show_the_online_link_instead_of_a_room(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $other = $this->studentUser('Морозов Кирилл', 'ПИ-22');
        $favorite = $this->teacherUser('Иванова А. С.');
        $ignored = $this->teacherUser('Петров Н. И.');
        $student->studentProfile->favoriteTeachers()->attach($favorite->teacherProfile->id);

        $this->publish($favorite, [
            'format' => 'online',
            'start_time' => '16:00',
            'end_time' => '16:45',
            'comment' => 'подключиться за пять минут',
            'meeting_url' => 'https://meet.example.com/ivanova',
        ])->assertRedirect();
        $this->publish($ignored, [
            'format' => 'online',
            'start_time' => '18:00',
            'end_time' => '18:45',
            'meeting_url' => 'https://meet.example.com/petrov',
        ])->assertRedirect();

        $slot = Slot::query()->where('meeting_url', 'https://meet.example.com/ivanova')->firstOrFail();

        $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('https://meet.example.com/ivanova')
            ->assertSee('подключиться за пять минут')
            ->assertDontSee('https://meet.example.com/petrov')
            ->assertDontSee('ауд.');

        $this->actingAs($student)
            ->get(route('bookings.create', $slot))
            ->assertOk()
            ->assertSee('https://meet.example.com/ivanova')
            ->assertDontSee('ауд.');

        $this->actingAs($student)->post(route('bookings.store', $slot))->assertRedirect(route('bookings.index'));
        $this->actingAs($student)->post(route('bookings.store', $slot))->assertRedirect(route('bookings.index'));
        $this->actingAs($other)->post(route('bookings.store', $slot))->assertRedirect(route('bookings.index'));

        $this->assertSame(2, Booking::query()->where('status', Booking::BOOKED)->count());

        $booking = Booking::query()->where('student_profile_id', $student->studentProfile->id)->firstOrFail();

        $this->actingAs($student)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('https://meet.example.com/ivanova')
            ->assertSee('подключиться за пять минут')
            ->assertDontSee('ауд.');

        $this->actingAs($student)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('https://meet.example.com/ivanova')
            ->assertSee('подключиться за пять минут')
            ->assertDontSee('ауд.');
    }

    public function test_online_slot_rejects_an_overlapping_active_booking_and_can_be_cancelled(): void
    {
        $student = $this->studentUser();
        $teacher = $this->teacherUser('Иванова А. С.');
        $otherTeacher = $this->teacherUser('Петров Н. И.');
        $inPerson = $this->futureSlot($teacher, '10:00', '11:00', 'А', '101');
        $this->publish($otherTeacher, [
            'format' => 'online',
            'start_time' => '10:30',
            'end_time' => '11:30',
            'meeting_url' => 'http://meet.example.com/overlap',
        ])->assertRedirect();
        $online = Slot::query()->whereNotNull('meeting_url')->firstOrFail();

        $this->actingAs($student)->post(route('bookings.store', $inPerson))->assertRedirect();
        $this->actingAs($student)
            ->from(route('bookings.create', $online))
            ->post(route('bookings.store', $online))
            ->assertSessionHasErrors('slot');

        $this->assertSame(1, Booking::query()->count());

        $clear = $this->futureSlot($otherTeacher, '15:00', '15:45', 'Б', '12');
        $clear->update(['building' => null, 'room' => null, 'meeting_url' => 'https://meet.example.com/later']);

        $this->actingAs($student)->post(route('bookings.store', $clear))->assertRedirect();
        $booking = Booking::query()->where('slot_id', $clear->id)->firstOrFail();

        $this->actingAs($student)
            ->get(route('bookings.cancel', $booking))
            ->assertOk()
            ->assertSee('Отмена сохраняется. Слот для остальных студентов остаётся открытым.')
            ->assertDontSee('ауд.');

        $this->actingAs($student)
            ->post(route('bookings.cancel.store', $booking))
            ->assertRedirect(route('bookings.index'));

        $this->assertSame(1, Booking::query()->whereKey($booking->id)->count());
        $this->assertSame(Booking::CANCELLED_BY_STUDENT, $booking->refresh()->status);
        $this->assertTrue($clear->refresh()->isFree());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publish(User $teacher, array $overrides = [])
    {
        return $this->actingAs($teacher)->post(route('teacher.slots.store'), array_merge([
            'format' => 'in_person',
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '16:00',
            'end_time' => '16:45',
            'building' => 'Главный Б',
            'room' => '220',
        ], $overrides));
    }
}
