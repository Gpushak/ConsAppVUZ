<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use Tests\ConsultationTestCase;

class ListPresentationTest extends ConsultationTestCase
{
    public function test_teacher_hides_conducted_and_unpublished_rows_and_keeps_other_statuses(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $student = $this->studentUser('Соколов Илья');

        $open = $this->futureSlot($teacher, '10:00', '10:45', 'Главный А', '314');
        $cancelled = $this->pastSlot($teacher, '210');
        Booking::query()->create([
            'slot_id' => $cancelled->id,
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::CANCELLED_BY_TEACHER,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $teacher->id,
        ]);
        $conducted = $this->pastSlot($teacher, '777');
        $conducted->update(['conducted_at' => now()->subHour()]);
        Booking::query()->create([
            'slot_id' => $conducted->id,
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::COMPLETED,
            'completed_at' => now()->subHour(),
        ]);
        $removed = $this->futureSlot($teacher, '16:00', '16:45', 'Главный А', '909');
        $removed->update(['is_published' => false]);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.slots.index'))
            ->assertOk()
            ->assertSee('Мои слоты')
            ->assertSee('Показать завершённые')
            ->assertDontSee('Слоты и записавшиеся')
            ->getContent();

        $this->assertRowFinished($html, 'ауд. 777', true);
        $this->assertRowFinished($html, 'ауд. 909', true);
        $this->assertRowFinished($html, 'ауд. 314', false);
        $this->assertRowFinished($html, 'ауд. 210', false);
        $this->assertStringContainsString('свободен', $html);
        $this->assertStringContainsString('отменён преподавателем', $html);
        $this->assertStringContainsString('проведён', $html);
        $this->assertStringContainsString('снят', $html);
        $this->assertStringNotContainsString('class="lead"', $html);
    }

    public function test_student_lists_hide_conducted_rows_and_keep_cancellations(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $teacher = $this->teacherUser('Иванова А. С.');
        $student->studentProfile->favoriteTeachers()->attach($teacher->teacherProfile->id);

        $open = $this->futureSlot($teacher, '10:00', '10:45', 'Главный А', '314');
        Booking::query()->create([
            'slot_id' => $open->id,
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::BOOKED,
        ]);
        $conducted = $this->pastSlot($teacher, '777');
        $conducted->update(['conducted_at' => now()->subHour()]);
        Booking::query()->create([
            'slot_id' => $conducted->id,
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::COMPLETED,
            'completed_at' => now()->subHour(),
        ]);
        $cancelled = $this->pastSlot($teacher, '210');
        Booking::query()->create([
            'slot_id' => $cancelled->id,
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::CANCELLED_BY_STUDENT,
            'cancelled_at' => $cancelled->starts_at,
            'cancelled_by_user_id' => $student->id,
        ]);
        $removed = $this->futureSlot($teacher, '16:00', '16:45', 'Главный А', '909');
        $removed->update(['is_published' => false]);

        $home = $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Слоты избранных')
            ->assertSee('Показать завершённые')
            ->assertSee('ауд. 314')
            ->assertSee('ауд. 777')
            ->assertDontSee('ауд. 909')
            ->assertDontSee('Предстоящие консультации')
            ->getContent();

        $this->assertRowFinished($home, 'ауд. 777', true);
        $this->assertRowFinished($home, 'ауд. 314', false);
        $this->assertStringNotContainsString('ауд. 210', $home);

        $bookings = $this->actingAs($student)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('Мои записи')
            ->assertSee('Показать завершённые')
            ->assertSee('занят')
            ->assertSee('проведён')
            ->assertSee('отменён студентом')
            ->assertDontSee('Отменить можно только свою запись')
            ->getContent();

        $this->assertRowFinished($bookings, 'ауд. 777', true);
        $this->assertRowFinished($bookings, 'ауд. 314', false);
        $this->assertRowFinished($bookings, 'ауд. 210', false);
    }

    public function test_page_headings_drop_the_lead_and_confirm_warnings_stay(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $student = $this->studentUser('Соколов Илья');
        $admin = $this->adminUser();
        $slot = $this->futureSlot($teacher, '10:00', '10:45', 'Главный А', '314');
        $booking = Booking::query()->create([
            'slot_id' => $slot->id,
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::BOOKED,
        ]);

        $create = $this->actingAs($teacher)
            ->get(route('teacher.slots.create'))
            ->assertOk()
            ->assertSee('Новый слот')
            ->assertSee('Формат')
            ->assertSee('Дата')
            ->assertDontSee('Окно групповое')
            ->getContent();
        $this->assertStringNotContainsString('class="lead"', $create);

        $teachers = $this->actingAs($student)
            ->get(route('teachers.index'))
            ->assertOk()
            ->assertSee('Преподаватели')
            ->assertSee('Поиск')
            ->assertDontSee('Все преподаватели кафедры')
            ->getContent();
        $this->assertStringNotContainsString('class="lead"', $teachers);

        $users = $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Пользователи')
            ->assertDontSee('Учётные записи кафедры')
            ->getContent();
        $this->assertStringNotContainsString('class="lead"', $users);

        $started = $this->pastSlot($teacher, '101');
        $started->bookings()->create([
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::BOOKED,
        ]);
        $conduct = $this->actingAs($teacher)
            ->get(route('teacher.slots.conduct', $started))
            ->assertOk()
            ->assertSee('Отметить проведённой')
            ->assertSee('Активные записи получат статус «проведён».')
            ->getContent();
        $this->assertStringNotContainsString('class="lead"', $conduct);

        $empty = $this->futureSlot($teacher, '18:00', '18:45', 'Б', '12');
        $unpublish = $this->actingAs($teacher)
            ->get(route('teacher.slots.unpublish.confirm', $empty))
            ->assertOk()
            ->assertSee('Снять с публикации')
            ->assertSee('Слот исчезнет из кабинета студента. Вернуть его в публикацию нельзя.')
            ->getContent();
        $this->assertStringNotContainsString('class="lead"', $unpublish);

        $free = $this->futureSlot($teacher, '15:00', '15:45', 'В', '105');
        $confirm = $this->actingAs($student)
            ->get(route('bookings.create', $free))
            ->assertOk()
            ->assertSee('Подтверждение записи')
            ->assertDontSee('Слот групповой')
            ->getContent();
        $this->assertStringNotContainsString('class="lead"', $confirm);

        $cancel = $this->actingAs($student)
            ->get(route('bookings.cancel', $booking))
            ->assertOk()
            ->assertSee('Отмена сохраняется. Слот для остальных студентов остаётся открытым.')
            ->getContent();
        $this->assertStringNotContainsString('class="lead"', $cancel);

        $teacherCancel = $this->actingAs($teacher)
            ->get(route('teacher.bookings.cancel', $booking))
            ->assertOk()
            ->assertSee('Отменяется запись одного студента. Остальные остаются, новая запись на слот возможна.')
            ->getContent();
        $this->assertStringNotContainsString('class="lead"', $teacherCancel);

        $block = $this->actingAs($admin)
            ->get(route('admin.users.block.confirm', $student))
            ->assertOk()
            ->assertSee('Вход станет недоступен, открытая сессия прервётся.')
            ->getContent();
        $this->assertStringNotContainsString('class="lead"', $block);
    }

    private function pastSlot(User $teacher, string $room): Slot
    {
        return Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => now()->subDay()->setTime(10, 0),
            'ends_at' => now()->subDay()->setTime(10, 45),
            'building' => 'Главный А',
            'room' => $room,
            'is_published' => true,
        ]);
    }

    private function assertRowFinished(string $html, string $marker, bool $finished): void
    {
        $pattern = '/<tr([^>]*)>(?:(?!<\/tr>).)*'.preg_quote($marker, '/').'/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        if ($finished) {
            $this->assertStringContainsString('data-finished', $match[1]);
            $this->assertStringContainsString('hidden', $match[1]);
        } else {
            $this->assertStringNotContainsString('data-finished', $match[1]);
        }
    }
}
