<?php

namespace Tests;

use App\Models\Role;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

abstract class ConsultationTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function studentUser(string $name = 'Студент Тест', string $group = 'ПИ-21'): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'role_id' => Role::idFor(Role::STUDENT),
        ]);
        $user->studentProfile()->create(['group_name' => $group]);

        return $user->fresh(['studentProfile', 'role']);
    }

    protected function adminUser(string $name = 'Администратор кафедры'): User
    {
        return User::factory()->create([
            'name' => $name,
            'role_id' => Role::idFor(Role::ADMIN),
        ]);
    }

    protected function teacherUser(string $name = 'Преподаватель Тест'): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'role_id' => Role::idFor(Role::TEACHER),
        ]);
        $user->teacherProfile()->create([
            'position' => 'доцент',
            'department' => 'программная инженерия',
        ]);

        return $user->fresh(['teacherProfile', 'role']);
    }

    protected function futureSlot(
        User $teacher,
        string $start,
        string $end,
        string $building = 'А',
        string $room = '101',
    ): Slot {
        $day = now()->addDays(3)->toDateString();

        return Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => $day.' '.$start.':00',
            'ends_at' => $day.' '.$end.':00',
            'building' => $building,
            'room' => $room,
            'is_published' => true,
        ]);
    }
}
