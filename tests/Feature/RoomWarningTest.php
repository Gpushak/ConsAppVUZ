<?php

namespace Tests\Feature;

use App\Models\Slot;
use App\Models\User;
use Tests\ConsultationTestCase;

class RoomWarningTest extends ConsultationTestCase
{
    public function test_same_room_and_overlapping_time_shows_the_other_teacher_and_clock_times(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $other = $this->teacherUser('Петров Н. И.');
        $this->occupy($other, '2027-05-29', '12:00', '12:45');

        $query = [
            'format' => 'in_person',
            'building' => 'Б',
            'room' => '210',
            'date' => '2027-05-29',
            'start_time' => '12:15',
            'end_time' => '13:00',
        ];

        $page = $this->actingAs($teacher)
            ->get(route('teacher.slots.create', $query))
            ->assertOk()
            ->assertSee('Аудитория уже занята в это время. Слот всё равно можно опубликовать.')
            ->assertSee('Петров Н. И.', false)
            ->assertSee('29.05.2027', false)
            ->assertSee('12:00–12:45', false)
            ->assertSee('Опубликовать')
            ->getContent();

        $this->assertStringNotContainsString('14:00', $page);
        $this->assertStringNotContainsString('class="notice error"', $page);

        $this->actingAs($teacher)
            ->get(route('teacher.slots.room-warning', $query))
            ->assertOk()
            ->assertSee('Петров Н. И.', false)
            ->assertSee('29.05.2027', false)
            ->assertSee('12:00–12:45', false);

        $this->occupy($other, '2027-05-29', '10:00', '10:45', 'Б', '210');
        $minute = $query;
        $minute['start_time'] = '10:44';
        $minute['end_time'] = '11:30';

        $this->actingAs($teacher)
            ->get(route('teacher.slots.create', $minute))
            ->assertOk()
            ->assertSee('Петров Н. И.', false)
            ->assertSee('10:00–10:45', false);
    }

    public function test_a_slot_that_only_touches_at_the_boundary_does_not_warn(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $other = $this->teacherUser('Петров Н. И.');
        $this->occupy($other, '2027-05-29', '12:00', '12:45');

        $base = [
            'format' => 'in_person',
            'building' => 'Б',
            'room' => '210',
            'date' => '2027-05-29',
        ];

        foreach ([['11:00', '12:00'], ['12:45', '13:30']] as [$start, $end]) {
            $page = $this->actingAs($teacher)
                ->get(route('teacher.slots.create', $base + [
                    'start_time' => $start,
                    'end_time' => $end,
                ]))
                ->assertOk()
                ->getContent();

            $this->assertStringNotContainsString('Петров Н. И.', $page);
            $this->assertStringNotContainsString('Аудитория уже занята', $page);
        }
    }

    public function test_online_slot_does_not_warn(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $other = $this->teacherUser('Петров Н. И.');
        $this->occupy($other, '2027-05-29', '12:00', '12:45');
        Slot::query()->create([
            'teacher_profile_id' => $other->teacherProfile->id,
            'starts_at' => '2027-05-29 16:00:00',
            'ends_at' => '2027-05-29 16:45:00',
            'building' => null,
            'room' => null,
            'meeting_url' => 'https://meet.example.com/petrov',
            'is_published' => true,
        ]);

        $online = $this->actingAs($teacher)
            ->get(route('teacher.slots.create', [
                'format' => 'online',
                'building' => 'Б',
                'room' => '210',
                'date' => '2027-05-29',
                'start_time' => '12:00',
                'end_time' => '12:45',
                'meeting_url' => 'https://meet.example.com/ivanova',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Петров Н. И.', $online);
        $this->assertStringNotContainsString('Аудитория уже занята', $online);

        $inPerson = $this->actingAs($teacher)
            ->get(route('teacher.slots.create', [
                'format' => 'in_person',
                'building' => 'Б',
                'room' => '210',
                'date' => '2027-05-29',
                'start_time' => '16:00',
                'end_time' => '16:45',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Аудитория уже занята', $inPerson);
    }

    public function test_teacher_can_still_save_after_the_warning(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $other = $this->teacherUser('Петров Н. И.');
        $this->occupy($other, '2027-05-29', '12:00', '12:45');

        $payload = [
            'format' => 'in_person',
            'building' => 'Б',
            'room' => '210',
            'date' => '29.05.2027',
            'start_time' => '12:15',
            'end_time' => '13:00',
            'repeat' => 'none',
        ];

        $this->actingAs($teacher)
            ->get(route('teacher.slots.create', $payload))
            ->assertOk()
            ->assertSee('Петров Н. И.', false)
            ->assertSee('29.05.2027', false)
            ->assertSee('12:00–12:45', false);

        $this->actingAs($teacher)
            ->post(route('teacher.slots.store'), $payload)
            ->assertRedirect(route('teacher.slots.index'))
            ->assertSessionHas('status', 'Слот опубликован.');

        $this->assertSame(2, Slot::query()->count());
        $created = Slot::query()->where('teacher_profile_id', $teacher->teacherProfile->id)->firstOrFail();
        $this->assertTrue($created->is_published);
        $this->assertSame('Б', $created->building);
        $this->assertSame('210', $created->room);
        $this->assertSame('12:15', $created->starts_at->format('H:i'));
        $this->assertSame('13:00', $created->ends_at->format('H:i'));
        $this->assertTrue($other->teacherProfile->slots()->first()->is_published);
    }

    public function test_another_room_or_a_different_time_does_not_warn(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $other = $this->teacherUser('Петров Н. И.');
        $this->occupy($other, '2027-05-29', '12:00', '12:45', 'Б', '210');

        foreach ([
            ['building' => 'А', 'room' => '210', 'start_time' => '12:00', 'end_time' => '12:45'],
            ['building' => 'Б', 'room' => '211', 'start_time' => '12:00', 'end_time' => '12:45'],
            ['building' => 'Б', 'room' => '210', 'start_time' => '13:00', 'end_time' => '13:45'],
        ] as $override) {
            $page = $this->actingAs($teacher)
                ->get(route('teacher.slots.create', [
                    'format' => 'in_person',
                    'date' => '2027-05-29',
                ] + $override))
                ->assertOk()
                ->getContent();

            $this->assertStringNotContainsString('Аудитория уже занята', $page);
        }
    }

    public function test_unpublished_slot_does_not_warn_and_own_slot_does(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $taken = $this->occupy($teacher, '2027-05-28', '11:00', '11:45', 'Главный А', '314');
        $taken->update(['is_published' => false]);

        $hidden = $this->actingAs($teacher)
            ->get(route('teacher.slots.create', [
                'format' => 'in_person',
                'building' => 'Главный А',
                'room' => '314',
                'date' => '2027-05-28',
                'start_time' => '11:00',
                'end_time' => '11:45',
            ]))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('Аудитория уже занята', $hidden);

        $taken->update(['is_published' => true]);

        $this->actingAs($teacher)
            ->get(route('teacher.slots.create', [
                'format' => 'in_person',
                'building' => 'Главный А',
                'room' => '314',
                'date' => '2027-05-28',
                'start_time' => '11:20',
                'end_time' => '12:05',
            ]))
            ->assertOk()
            ->assertSee('Иванова А. С.', false)
            ->assertSee('28.05.2027', false)
            ->assertSee('11:00–11:45', false);
    }

    public function test_several_overlaps_are_listed(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $petrov = $this->teacherUser('Петров Н. И.');
        $smirnova = $this->teacherUser('Смирнова Е. В.');
        $this->occupy($petrov, '2027-05-29', '12:00', '12:45');
        $this->occupy($smirnova, '2027-05-29', '12:30', '13:15');

        $page = $this->actingAs($teacher)
            ->get(route('teacher.slots.create', [
                'format' => 'in_person',
                'building' => 'Б',
                'room' => '210',
                'date' => '2027-05-29',
                'start_time' => '12:15',
                'end_time' => '13:00',
            ]))
            ->assertOk()
            ->assertSee('Петров Н. И.', false)
            ->assertSee('12:00–12:45', false)
            ->assertSee('Смирнова Е. В.', false)
            ->assertSee('12:30–13:15', false)
            ->getContent();

        $this->assertLessThan(
            strpos($page, '12:30–13:15'),
            strpos($page, '12:00–12:45'),
        );
    }

    public function test_series_checks_every_day_it_would_create(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $other = $this->teacherUser('Петров Н. И.');
        $this->occupy($other, '2026-11-03', '10:00', '10:45', 'Г', '12');

        $open = [
            'format' => 'in_person',
            'building' => 'Г',
            'room' => '12',
            'date' => '2026-10-27',
            'start_time' => '10:00',
            'end_time' => '10:45',
            'repeat' => '1',
        ];

        $waiting = $this->actingAs($teacher)
            ->get(route('teacher.slots.create', $open))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('Аудитория уже занята', $waiting);

        $firstOnly = $this->actingAs($teacher)
            ->get(route('teacher.slots.create', $open + ['repeat_until' => '2026-10-27']))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('Аудитория уже занята', $firstOnly);

        $this->actingAs($teacher)
            ->get(route('teacher.slots.create', $open + ['repeat_until' => '03.11.2026']))
            ->assertOk()
            ->assertSee('Петров Н. И.', false)
            ->assertSee('03.11.2026', false)
            ->assertSee('10:00–10:45', false);

        $this->occupy($other, '2026-11-03', '15:00', '15:45', 'Д', '4');
        $between = $this->actingAs($teacher)
            ->get(route('teacher.slots.create', [
                'format' => 'in_person',
                'building' => 'Д',
                'room' => '4',
                'date' => '2026-10-27',
                'start_time' => '15:00',
                'end_time' => '15:45',
                'repeat' => '2',
                'repeat_until' => '24.11.2026',
            ]))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('Аудитория уже занята', $between);

        $this->occupy($other, '2026-11-10', '15:00', '15:45', 'Д', '4');
        $this->actingAs($teacher)
            ->get(route('teacher.slots.create', [
                'format' => 'in_person',
                'building' => 'Д',
                'room' => '4',
                'date' => '27.10.2026',
                'start_time' => '15:00',
                'end_time' => '15:45',
                'repeat' => '2',
                'repeat_until' => '2026-11-24',
            ]))
            ->assertOk()
            ->assertSee('Петров Н. И.', false)
            ->assertSee('10.11.2026', false)
            ->assertSee('15:00–15:45', false)
            ->assertDontSee('03.11.2026');
    }

    private function occupy(
        User $teacher,
        string $day,
        string $start,
        string $end,
        string $building = 'Б',
        string $room = '210',
    ): Slot {
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
