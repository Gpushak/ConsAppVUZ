<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public const PASSWORD = 'Parol-123';

    public function run(): void
    {
        if (User::query()->where('email', 'admin@kafedra.local')->exists()) {
            return;
        }

        $at = function (int $offsetDays, int $hour, int $minute): Carbon {
            return now()->startOfDay()->addDays($offsetDays)->setTime($hour, $minute);
        };

        $person = function (string $role, string $name, string $email, bool $active = true): User {
            return User::query()->create([
                'role_id' => Role::idFor($role),
                'name' => $name,
                'email' => $email,
                'password' => self::PASSWORD,
                'is_active' => $active,
                'must_change_password' => false,
            ]);
        };

        $person(Role::ADMIN, 'Администратор кафедры', 'admin@kafedra.local');

        $ivanovaUser = $person(Role::TEACHER, 'Иванова А. С.', 'ivanova@kafedra.local');
        $petrovUser = $person(Role::TEACHER, 'Петров Н. И.', 'petrov@kafedra.local');
        $smirnovaUser = $person(Role::TEACHER, 'Смирнова Е. В.', 'smirnova@kafedra.local');

        $ivanova = $ivanovaUser->teacherProfile()->create([
            'position' => 'доцент',
            'department' => 'программная инженерия',
        ]);
        $petrov = $petrovUser->teacherProfile()->create([
            'position' => 'старший преподаватель',
            'department' => 'программная инженерия',
        ]);
        $smirnova = $smirnovaUser->teacherProfile()->create([
            'position' => 'профессор',
            'department' => 'программная инженерия',
        ]);

        $student = function (string $name, string $email, string $group, bool $active = true) use ($person): User {
            $user = $person(Role::STUDENT, $name, $email, $active);
            $user->studentProfile()->create(['group_name' => $group]);

            return $user->fresh('studentProfile');
        };

        $sokolov = $student('Соколов Илья', 'sokolov@kafedra.local', 'ПИ-21');
        $belova = $student('Белова Анна', 'belova@kafedra.local', 'ПИ-21');
        $orlova = $student('Орлова Марина', 'orlova@kafedra.local', 'ПИ-21');
        $volkova = $student('Волкова Дарья', 'volkova@kafedra.local', 'ПИ-21');
        $morozov = $student('Морозов Кирилл', 'morozov@kafedra.local', 'ПИ-22');
        $lebedeva = $student('Лебедева Ольга', 'lebedeva@kafedra.local', 'ПИ-22');
        $novikov = $student('Новиков Игорь', 'novikov@kafedra.local', 'ПИ-22');
        $student('Кузнецова Мария', 'kuznetsova@kafedra.local', 'ПИ-22', false);
        $egorov = $student('Егоров Артём', 'egorov@kafedra.local', 'ПИ-31');

        $sokolov->studentProfile->favoriteTeachers()->attach([$ivanova->id, $smirnova->id]);

        $slot = function (object $teacher, Carbon $start, Carbon $end, string $building, string $room, ?string $comment = null, ?string $url = null) {
            return $teacher->slots()->create([
                'starts_at' => $start,
                'ends_at' => $end,
                'building' => $building,
                'room' => $room,
                'comment' => $comment,
                'meeting_url' => $url,
                'is_published' => true,
            ]);
        };

        $book = function (Slot $slot, User $student, string $status, ?string $comment = null, ?User $actor = null): void {
            Booking::query()->create([
                'slot_id' => $slot->id,
                'student_profile_id' => $student->studentProfile->id,
                'status' => $status,
                'teacher_comment' => $comment,
                'cancelled_at' => in_array($status, [Booking::CANCELLED_BY_STUDENT, Booking::CANCELLED_BY_TEACHER], true)
                    ? $slot->starts_at
                    : null,
                'cancelled_by_user_id' => in_array($status, [Booking::CANCELLED_BY_STUDENT, Booking::CANCELLED_BY_TEACHER], true)
                    ? ($actor?->id ?? $student->id)
                    : null,
                'completed_at' => $status === Booking::COMPLETED ? $slot->ends_at : null,
            ]);
        };

        $conducted = $slot($ivanova, $at(-17, 10, 0), $at(-17, 10, 45), 'Главный А', '314');
        $book($conducted, $sokolov, Booking::COMPLETED);
        $conducted->update(['conducted_at' => $conducted->ends_at]);

        $teacherCancelled = $slot($ivanova, $at(-16, 14, 0), $at(-16, 14, 30), 'Главный А', '314');
        $book($teacherCancelled, $sokolov, Booking::CANCELLED_BY_TEACHER, 'не смогу быть на кафедре', $ivanovaUser);

        $studentCancelled = $slot($ivanova, $at(-13, 10, 0), $at(-13, 10, 45), 'Главный А', '314');
        $book($studentCancelled, $sokolov, Booking::CANCELLED_BY_STUDENT, null, $sokolov);

        $pastTwo = $slot($ivanova, $at(-3, 10, 0), $at(-3, 10, 45), 'Главный А', '314');
        $book($pastTwo, $lebedeva, Booking::BOOKED);
        $book($pastTwo, $egorov, Booking::BOOKED);

        $pastComment = $slot($ivanova, $at(-1, 14, 0), $at(-1, 14, 45), 'Главный А', '314', 'принести черновик курсовой');
        $book($pastComment, $orlova, Booking::BOOKED);
        $book($pastComment, $lebedeva, Booking::BOOKED);

        $homeDraft = $slot($ivanova, $at(2, 10, 0), $at(2, 10, 45), 'Главный А', '314', 'принести черновик курсовой');
        $book($homeDraft, $orlova, Booking::BOOKED);
        $book($homeDraft, $lebedeva, Booking::BOOKED);

        $groupSlot = $slot($ivanova, $at(4, 11, 0), $at(4, 11, 45), 'Главный А', '314');
        $book($groupSlot, $belova, Booking::BOOKED);
        $book($groupSlot, $sokolov, Booking::BOOKED);
        $book($groupSlot, $morozov, Booking::BOOKED);

        $smirnovaPast = $slot($smirnova, $at(-9, 15, 0), $at(-9, 15, 45), 'В', '105');
        $book($smirnovaPast, $sokolov, Booking::CANCELLED_BY_TEACHER, 'опубликую другое окно', $smirnovaUser);

        $smirnovaNext = $slot(
            $smirnova,
            $at(3, 15, 0),
            $at(3, 15, 45),
            'В',
            '105',
            'разбор лабораторной',
            'https://meet.example.com/lab',
        );
        $book($smirnovaNext, $volkova, Booking::BOOKED);
        $book($smirnovaNext, $novikov, Booking::BOOKED);

        $petrovPast = $slot($petrov, $at(-10, 12, 0), $at(-10, 12, 30), 'Б', '210');
        $book($petrovPast, $sokolov, Booking::CANCELLED_BY_STUDENT, null, $sokolov);

        $slot($petrov, $at(8, 12, 0), $at(8, 12, 45), 'Б', '210');

        $ivanova->slots()->create([
            'starts_at' => $at(1, 16, 0),
            'ends_at' => $at(1, 16, 45),
            'building' => null,
            'room' => null,
            'comment' => 'подключиться за пять минут',
            'meeting_url' => 'https://meet.example.com/ivanova',
            'is_published' => true,
        ]);
    }
}
