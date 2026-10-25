<?php

namespace Tests\Feature;

use App\Models\Slot;
use Tests\ConsultationTestCase;

class SlotBuildingTest extends ConsultationTestCase
{
    public function test_building_outside_the_list_is_rejected(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');

        $this->actingAs($teacher)
            ->post(route('teacher.slots.store'), [
                'date' => now()->addDay()->toDateString(),
                'start_time' => '16:00',
                'end_time' => '16:45',
                'building' => 'Корпус 9',
                'room' => '220',
            ])
            ->assertSessionHasErrors('building');

        $this->assertSame(0, Slot::query()->count());
    }
}
