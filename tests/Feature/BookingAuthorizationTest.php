<?php

namespace Tests\Feature;

use App\Models\Booking;
use Tests\ConsultationTestCase;

class BookingAuthorizationTest extends ConsultationTestCase
{
    public function test_a_student_cannot_open_another_students_booking(): void
    {
        $owner = $this->studentUser('Соколов Илья', 'ПИ-21');
        $other = $this->studentUser('Белова Анна', 'ПИ-21');
        $teacher = $this->teacherUser('Иванова А. С.');
        $slot = $this->futureSlot($teacher, '11:00', '11:45', 'Главный А', '314');

        $this->actingAs($owner)->post(route('bookings.store', $slot))->assertRedirect();
        $booking = Booking::query()->firstOrFail();

        $this->actingAs($other)
            ->get(route('bookings.show', $booking))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Соколов Илья');
    }
}
