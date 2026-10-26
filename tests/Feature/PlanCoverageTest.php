<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\ConsultationTestCase;

class PlanCoverageTest extends ConsultationTestCase
{
    public function test_student_registration_opens_the_student_cabinet(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Регистрация студента')
            ->assertSee('Повтор пароля');

        $this->post(route('register.store'), [
            'name' => 'Новиков Пётр',
            'group_name' => 'ПИ-21',
            'email' => 'novikov.petr@kafedra.local',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->post(route('register.store'), [
            'name' => 'Новиков Пётр',
            'group_name' => 'ПИ-21',
            'email' => 'novikov.petr@kafedra.local',
            'password' => 'Parol-123',
            'password_confirmation' => 'Drugoi-123',
        ])->assertSessionHasErrors('password');

        $this->post(route('register.store'), [
            'name' => 'Новиков Пётр',
            'group_name' => 'ПИ-21',
            'email' => 'novikov.petr@kafedra.local',
            'password' => 'Parol-123',
            'password_confirmation' => 'Parol-123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticated();
        $user = User::query()->where('email', 'novikov.petr@kafedra.local')->firstOrFail();
        $this->assertTrue($user->isStudent());
        $this->assertSame('ПИ-21', $user->studentProfile->group_name);
        $this->assertTrue(Hash::check('Parol-123', $user->password));
        $this->get(route('home'))->assertOk()->assertSee('Избранных преподавателей нет');
    }

    public function test_admin_sets_a_temporary_password_and_teacher_must_change_it(): void
    {
        $admin = $this->adminUser();
        $teacher = $this->teacherUser('Иванова А. С.');
        $teacher->update(['email' => 'ivanova@kafedra.local']);
        $this->rememberSession($teacher);

        $this->actingAs($admin)
            ->post(route('admin.users.password', $teacher), ['password' => 'Vremen-123'])
            ->assertRedirect(route('admin.users.index'));

        $teacher->refresh();
        $this->assertTrue($teacher->must_change_password);
        $this->assertTrue(Hash::check('Vremen-123', $teacher->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $teacher->id]);

        $this->asGuest();
        $this->post(route('login.store'), [
            'email' => 'ivanova@kafedra.local',
            'password' => 'Vremen-123',
        ])->assertRedirect(route('password.edit'));

        $this->get(route('password.edit'))
            ->assertOk()
            ->assertSee('Временный пароль нужно сменить до входа в кабинет.');
        $this->get(route('teacher.slots.index'))->assertRedirect(route('password.edit'));
        $this->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '16:00',
            'end_time' => '16:45',
            'building' => 'А',
            'room' => '101',
        ])->assertRedirect(route('password.edit'));

        $this->post(route('password.update'), [
            'password' => 'Svoi-1234',
            'password_confirmation' => 'Svoi-1234',
        ])->assertRedirect(route('home'));

        $this->assertFalse($teacher->refresh()->must_change_password);
        $this->get(route('teacher.slots.index'))->assertOk()->assertSee('Мои слоты');
    }

    public function test_admin_creates_a_teacher_who_must_change_the_temporary_password(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('admin.teachers.store'), [
            'name' => 'Новикова И. П.',
            'email' => 'novikova@kafedra.local',
            'position' => 'доцент',
            'department' => 'программная инженерия',
            'password' => 'Vremen-123',
        ])->assertRedirect(route('admin.users.index'));

        $teacher = User::query()->where('email', 'novikova@kafedra.local')->firstOrFail();
        $this->assertTrue($teacher->isTeacher());
        $this->assertTrue($teacher->must_change_password);
        $this->assertSame('доцент', $teacher->teacherProfile->position);
        $this->assertTrue(Hash::check('Vremen-123', $teacher->password));
    }

    public function test_block_and_unblock_end_the_session(): void
    {
        $admin = $this->adminUser();
        $student = $this->studentUser('Соколов Илья');
        $student->update(['email' => 'sokolov@kafedra.local']);
        $this->rememberSession($student, 'session-while-active');

        $this->actingAs($admin)
            ->post(route('admin.users.block', $student))
            ->assertRedirect(route('admin.users.index'));

        $this->assertFalse($student->refresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-while-active']);

        $this->actingAs($student->fresh())
            ->get(route('home'))
            ->assertRedirect(route('login'));

        $this->asGuest();
        $this->post(route('login.store'), [
            'email' => 'sokolov@kafedra.local',
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->rememberSession($student, 'session-while-blocked');
        $this->actingAs($admin)
            ->post(route('admin.users.unblock', $student))
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue($student->refresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-while-blocked']);

        $this->asGuest();
        $this->post(route('login.store'), [
            'email' => 'sokolov@kafedra.local',
            'password' => 'password',
        ])->assertRedirect(route('home'));
        $this->get(route('home'))->assertOk();
    }

    public function test_adding_a_favorite_shows_that_teachers_slots_on_home(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $ivanova = $this->teacherUser('Иванова А. С.');
        $petrov = $this->teacherUser('Петров Н. И.');
        $this->futureSlot($ivanova, '10:00', '10:45', 'Главный А', '314');
        $this->futureSlot($petrov, '12:00', '12:45', 'Б', '210');

        $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Избранных преподавателей нет');

        $this->actingAs($student)
            ->post(route('teachers.favorite', $ivanova->teacherProfile))
            ->assertRedirect(route('teachers.index'));

        $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Иванова А. С.')
            ->assertSee('ауд. 314')
            ->assertDontSee('Петров Н. И.')
            ->assertDontSee('ауд. 210');
    }

    public function test_teacher_cannot_publish_an_overlapping_open_slot(): void
    {
        $teacher = $this->teacherUser();
        $this->futureSlot($teacher, '10:00', '10:45', 'А', '101');

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:30',
            'end_time' => '11:15',
            'building' => 'Б',
            'room' => '202',
        ])->assertSessionHasErrors('start_time');

        $this->assertSame(1, Slot::query()->count());
    }

    public function test_unpublished_or_conducted_slot_does_not_occupy_time(): void
    {
        $teacher = $this->teacherUser();
        $day = now()->addDays(3)->toDateString();
        $unpublished = $this->futureSlot($teacher, '10:00', '10:45', 'А', '101');
        $unpublished->update(['is_published' => false]);

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => $day,
            'start_time' => '10:00',
            'end_time' => '10:45',
            'building' => 'Б',
            'room' => '202',
        ])->assertRedirect(route('teacher.slots.index'));

        $this->assertSame(2, Slot::query()->count());
    }

    public function test_slot_shorter_than_fifteen_minutes_is_rejected(): void
    {
        $teacher = $this->teacherUser();

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '16:00',
            'end_time' => '16:10',
            'building' => 'А',
            'room' => '101',
        ])->assertSessionHasErrors('end_time');

        $this->assertSame(0, Slot::query()->count());
    }

    public function test_cancel_keeps_the_row_and_the_slot_stays_open(): void
    {
        $student = $this->studentUser('Соколов Илья', 'ПИ-21');
        $other = $this->studentUser('Белова Анна', 'ПИ-21');
        $teacher = $this->teacherUser();
        $slot = $this->futureSlot($teacher, '11:00', '11:45', 'Главный А', '314');

        $this->actingAs($student)->post(route('bookings.store', $slot))->assertRedirect();
        $booking = Booking::query()->firstOrFail();

        $this->actingAs($student)
            ->post(route('bookings.cancel.store', $booking))
            ->assertRedirect(route('bookings.index'));

        $this->assertSame(1, Booking::query()->count());
        $this->assertSame(Booking::CANCELLED_BY_STUDENT, $booking->refresh()->status);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertTrue($slot->refresh()->isFree());

        $this->actingAs($other)->post(route('bookings.store', $slot))->assertRedirect();
        $this->assertSame(2, Booking::query()->count());
        $this->assertSame(1, Booking::query()->where('status', Booking::BOOKED)->count());
    }

    public function test_teacher_marks_a_started_slot_conducted_and_cannot_do_it_early(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $teacher = $this->teacherUser('Иванова А. С.');
        $otherTeacher = $this->teacherUser('Петров Н. И.');
        $future = $this->futureSlot($teacher, '12:00', '12:45', 'А', '101');
        $this->actingAs($student)->post(route('bookings.store', $future))->assertRedirect();

        $this->actingAs($teacher)
            ->post(route('teacher.slots.conduct.store', $future))
            ->assertForbidden();
        $this->actingAs($otherTeacher)
            ->post(route('teacher.slots.unpublish', $future))
            ->assertForbidden();

        $started = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->subMinutes(15),
            'building' => 'Главный А',
            'room' => '314',
            'is_published' => true,
        ]);
        Booking::query()->create([
            'slot_id' => $started->id,
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::BOOKED,
        ]);

        $this->actingAs($teacher)->post(route('teacher.slots.conduct.store', $started), [
            'comment' => 'разобрали введение',
        ])->assertRedirect(route('teacher.slots.index'));

        $booking = Booking::query()->where('slot_id', $started->id)->firstOrFail();
        $this->assertSame(Booking::COMPLETED, $booking->status);
        $this->assertSame('разобрали введение', $booking->teacher_comment);
        $this->assertNotNull($booking->completed_at);
        $this->assertNotNull($started->refresh()->conducted_at);
        $this->assertSame(1, Booking::query()->where('slot_id', $started->id)->count());

        $this->actingAs($teacher)
            ->get(route('teacher.slots.index'))
            ->assertOk()
            ->assertSee('проведён')
            ->assertSee('разобрали введение');

        $this->actingAs($student)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('проведён')
            ->assertSee('разобрали введение');
    }

    public function test_signup_list_is_grouped_and_shows_the_booking_comment(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $slot = $this->futureSlot($teacher, '11:00', '11:45', 'Главный А', '314');
        $belova = $this->studentUser('Белова Анна', 'ПИ-21');
        $sokolov = $this->studentUser('Соколов Илья', 'ПИ-21');
        $morozov = $this->studentUser('Морозов Кирилл', 'ПИ-22');

        foreach ([$belova, $sokolov, $morozov] as $student) {
            $this->actingAs($student)->post(route('bookings.store', $slot))->assertRedirect();
        }

        $belovaBooking = Booking::query()
            ->where('student_profile_id', $belova->studentProfile->id)
            ->firstOrFail();

        $this->actingAs($teacher)->post(route('teacher.bookings.cancel.store', $belovaBooking), [
            'comment' => 'не смогу быть на кафедре',
        ])->assertRedirect(route('teacher.slots.index'));

        $this->assertSame(3, Booking::query()->count());
        $this->assertSame(Booking::CANCELLED_BY_TEACHER, $belovaBooking->refresh()->status);
        $this->assertSame(2, Booking::query()->where('status', Booking::BOOKED)->count());

        $this->actingAs($teacher)
            ->get(route('teacher.slots.index'))
            ->assertOk()
            ->assertSee('<details class="people"', false)
            ->assertSeeInOrder([
                'ПИ-21',
                'Белова Анна',
                'не смогу быть на кафедре',
                'Соколов Илья',
                'ПИ-22',
                'Морозов Кирилл',
            ]);
    }

    private function rememberSession(User $user, string $id = 'session-under-test'): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'test',
            'last_activity' => time(),
        ]);
    }

    private function asGuest(): void
    {
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }
}
