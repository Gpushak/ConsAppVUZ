<?php

namespace Tests\Feature;

use Tests\ConsultationTestCase;

class FavoriteHomeTest extends ConsultationTestCase
{
    public function test_home_lists_only_favorite_teachers_slots(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $ivanova = $this->teacherUser('Иванова А. С.');
        $smirnova = $this->teacherUser('Смирнова Е. В.');
        $petrov = $this->teacherUser('Петров Н. И.');
        $this->futureSlot($ivanova, '10:00', '10:45', 'Главный А', '314');
        $this->futureSlot($smirnova, '15:00', '15:45', 'В', '105');
        $this->futureSlot($petrov, '12:00', '12:45', 'Б', '210');

        $student->studentProfile->favoriteTeachers()->attach([
            $ivanova->teacherProfile->id,
            $smirnova->teacherProfile->id,
        ]);

        $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Иванова А. С.')
            ->assertSee('Смирнова Е. В.')
            ->assertSee('ауд. 314')
            ->assertSee('ауд. 105')
            ->assertDontSee('Петров Н. И.')
            ->assertDontSee('ауд. 210');
    }

    public function test_removing_a_favorite_removes_that_teachers_slots_from_home(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $ivanova = $this->teacherUser('Иванова А. С.');
        $smirnova = $this->teacherUser('Смирнова Е. В.');
        $this->futureSlot($ivanova, '10:00', '10:45', 'Главный А', '314');
        $this->futureSlot($smirnova, '15:00', '15:45', 'В', '105');
        $student->studentProfile->favoriteTeachers()->attach([
            $ivanova->teacherProfile->id,
            $smirnova->teacherProfile->id,
        ]);

        $this->actingAs($student)
            ->post(route('teachers.unfavorite', $ivanova->teacherProfile))
            ->assertRedirect(route('teachers.index'));

        $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('Иванова А. С.')
            ->assertDontSee('ауд. 314')
            ->assertSee('Смирнова Е. В.')
            ->assertSee('ауд. 105');
    }
}
