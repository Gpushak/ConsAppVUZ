<?php

namespace Tests\Feature;

use App\Models\Booking;
use Tests\ConsultationTestCase;

class BookingRulesTest extends ConsultationTestCase
{
    public function test_second_booking_of_the_same_student_on_the_same_slot_is_rejected(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $teacher = $this->teacherUser('Иванова А. С.');
        $slot = $this->futureSlot($teacher, '10:00', '10:45', 'Главный А', '314');

        $this->actingAs($student)->post(route('bookings.store', $slot))->assertRedirect(route('bookings.index'));
        $this->actingAs($student)->post(route('bookings.store', $slot))->assertRedirect(route('bookings.index'));

        $this->assertSame(1, Booking::query()->where('status', Booking::BOOKED)->count());
        $this->assertSame(1, Booking::query()->count());
    }

    public function test_overlapping_active_bookings_are_rejected(): void
    {
        $student = $this->studentUser();
        $firstTeacher = $this->teacherUser('Иванова А. С.');
        $secondTeacher = $this->teacherUser('Петров Н. И.');
        $first = $this->futureSlot($firstTeacher, '10:00', '11:00', 'А', '101');
        $second = $this->futureSlot($secondTeacher, '10:30', '11:30', 'Б', '202');

        $this->actingAs($student)->post(route('bookings.store', $first))->assertRedirect();
        $this->actingAs($student)
            ->from(route('bookings.create', $second))
            ->post(route('bookings.store', $second))
            ->assertSessionHasErrors('slot');

        $this->assertSame(1, Booking::query()->where('status', Booking::BOOKED)->count());
    }

    public function test_a_second_student_can_join_the_same_slot(): void
    {
        $first = $this->studentUser('Соколов Илья', 'ПИ-21');
        $second = $this->studentUser('Морозов Кирилл', 'ПИ-22');
        $teacher = $this->teacherUser('Иванова А. С.');
        $slot = $this->futureSlot($teacher, '11:00', '11:45', 'Главный А', '314');

        $this->actingAs($first)->post(route('bookings.store', $slot))->assertRedirect();
        $this->actingAs($second)->post(route('bookings.store', $slot))->assertRedirect();

        $this->assertSame(2, Booking::query()->where('slot_id', $slot->id)->where('status', Booking::BOOKED)->count());
    }
}
