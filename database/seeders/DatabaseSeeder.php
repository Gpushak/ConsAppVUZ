<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public const PASSWORD = 'Parol-123';

    public function run(): void
    {
        if (User::query()->where('email', 'admin@kafedra.local')->exists()) {
            return;
        }

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
        $student('Егоров Артём', 'egorov@kafedra.local', 'ПИ-31');

        $sokolov->studentProfile->favoriteTeachers()->attach([$ivanova->id, $smirnova->id]);

        $slot = function (
            object $teacher,
            Carbon $start,
            Carbon $end,
            ?string $building,
            ?string $room,
            ?string $comment = null,
            ?string $url = null,
            ?string $seriesId = null,
        ) {
            return $teacher->slots()->create([
                'starts_at' => $start,
                'ends_at' => $end,
                'building' => $building,
                'room' => $room,
                'comment' => $comment,
                'meeting_url' => $url,
                'series_id' => $seriesId,
                'series_weeks' => $seriesId === null ? null : 1,
                'is_published' => true,
            ]);
        };

        $book = function (Slot $slot, User $student, string $status): void {
            Booking::query()->create([
                'slot_id' => $slot->id,
                'student_profile_id' => $student->studentProfile->id,
                'status' => $status,
                'completed_at' => $status === Booking::COMPLETED ? $slot->ends_at : null,
            ]);
        };

        $conducted = $slot($ivanova, $this->at('2027-05-04', 10, 0), $this->at('2027-05-04', 10, 45), 'Главный А', '314');
        $book($conducted, $sokolov, Booking::COMPLETED);
        $conducted->update(['conducted_at' => $conducted->ends_at]);

        $seriesId = (string) Str::uuid();
        $seriesBooked = $slot(
            $ivanova,
            $this->at('2027-05-25', 10, 0),
            $this->at('2027-05-25', 10, 45),
            'Главный А',
            '314',
            'каждую неделю',
            null,
            $seriesId,
        );
        $book($seriesBooked, $sokolov, Booking::BOOKED);
        $slot(
            $ivanova,
            $this->at('2027-06-01', 10, 0),
            $this->at('2027-06-01', 10, 45),
            'Главный А',
            '314',
            'каждую неделю',
            null,
            $seriesId,
        );

        $slot(
            $ivanova,
            $this->at('2027-05-25', 16, 0),
            $this->at('2027-05-25', 16, 45),
            null,
            null,
            'подключиться за пять минут',
            'https://meet.example.com/ivanova',
        );

        $homeDraft = $slot(
            $ivanova,
            $this->at('2027-05-26', 10, 0),
            $this->at('2027-05-26', 10, 45),
            'Главный А',
            '314',
            'принести черновик курсовой',
        );
        $book($homeDraft, $orlova, Booking::BOOKED);
        $book($homeDraft, $lebedeva, Booking::BOOKED);

        $smirnovaLab = $slot(
            $smirnova,
            $this->at('2027-05-27', 15, 0),
            $this->at('2027-05-27', 15, 45),
            'В',
            '105',
            'разбор лабораторной',
        );
        $book($smirnovaLab, $volkova, Booking::BOOKED);
        $book($smirnovaLab, $novikov, Booking::BOOKED);

        $groupSlot = $slot($ivanova, $this->at('2027-05-28', 11, 0), $this->at('2027-05-28', 11, 45), 'Главный А', '314');
        $book($groupSlot, $belova, Booking::BOOKED);
        $book($groupSlot, $sokolov, Booking::BOOKED);
        $book($groupSlot, $morozov, Booking::BOOKED);

        $slot($petrov, $this->at('2027-05-29', 12, 0), $this->at('2027-05-29', 12, 45), 'Б', '210');
    }

    /**
     * Wall clock in Asia/Yekaterinburg. The same clock is stored in the app
     * timezone so the demo shows these hours rather than a converted Moscow time.
     */
    private function at(string $day, int $hour, int $minute): Carbon
    {
        return Carbon::parse(sprintf('%s %02d:%02d:00', $day, $hour, $minute), 'Asia/Yekaterinburg')
            ->shiftTimezone((string) config('app.timezone'));
    }
}
