<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Slot;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Support\Term;
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
        $egorov = $student('Егоров Артём', 'egorov@kafedra.local', 'ПИ-31');

        $sokolov->studentProfile->favoriteTeachers()->attach([$ivanova->id, $smirnova->id]);

        $conducted = $this->once($ivanova, '2026-11-10', 10, 0, 10, 45, 'Главный А', '314', null, null);
        $this->book($conducted, $sokolov, Booking::COMPLETED);
        $conducted->update(['conducted_at' => $conducted->ends_at]);

        $ivanovaWeekly = $this->byDay($this->series(
            $ivanova, '2026-11-18', 7, 10, 0, 10, 45, 'Главный А', '314', 'вопросы по курсу', null,
        ));
        $ivanovaOnline = $this->byDay($this->series(
            $ivanova, '2026-11-19', 14, 16, 0, 16, 45, null, null, 'подключиться за пять минут', 'https://meet.example.com/ivanova',
        ));
        $smirnovaLab = $this->byDay($this->series(
            $smirnova, '2026-11-18', 14, 15, 0, 15, 45, 'В', '105', 'разбор лабораторной', null,
        ));
        $smirnovaOther = $this->byDay($this->series(
            $smirnova, '2026-11-25', 14, 12, 0, 12, 45, 'Г', '220', 'консультация по заданию', null,
        ));
        $petrovWeekly = $this->byDay($this->series(
            $petrov, '2026-11-18', 7, 12, 0, 12, 45, 'Б', '210', null, null,
        ));
        $this->series(
            $petrov, '2026-11-20', 14, 16, 0, 16, 45, null, null, 'семинар по ссылке', 'https://meet.example.com/petrov',
        );

        $group = $this->once($ivanova, '2026-11-20', 11, 0, 11, 45, 'А', '410', null, null);
        $draft = $this->once($ivanova, '2026-11-24', 14, 0, 14, 45, 'Главный Б', '201', 'принести черновик курсовой', null);
        $petrovRoom = $this->once($petrov, '2026-11-27', 9, 0, 9, 45, 'Д', '18', null, null);
        $smirnovaOnline = $this->once($smirnova, '2026-11-27', 16, 0, 16, 45, null, null, 'короткая онлайн-встреча', 'https://meet.example.com/smirnova');

        $this->book($ivanovaWeekly['2026-11-18'], $sokolov);
        $this->book($ivanovaWeekly['2026-11-18'], $belova);
        $this->book($petrovWeekly['2026-11-18'], $morozov);
        $this->book($smirnovaLab['2026-11-18'], $volkova);
        $this->book($smirnovaLab['2026-11-18'], $novikov);
        $this->book($ivanovaOnline['2026-11-19'], $orlova);
        $this->book($group, $belova);
        $this->book($group, $sokolov);
        $this->book($group, $morozov);
        $this->book($draft, $orlova);
        $this->book($draft, $lebedeva);
        $this->book($ivanovaWeekly['2026-11-25'], $egorov);
        $this->book($smirnovaOther['2026-11-25'], $volkova);
        $this->book($petrovWeekly['2026-11-25'], $novikov);
        $this->book($petrovRoom, $lebedeva);
        $this->book($smirnovaOnline, $egorov);
        $this->book($ivanovaWeekly['2026-12-02'], $morozov);
        $this->book($petrovWeekly['2026-12-02'], $belova);
        $this->book($smirnovaLab['2026-12-02'], $sokolov);
        $this->book($smirnovaLab['2026-12-02'], $egorov);
        $this->book($ivanovaOnline['2026-12-03'], $orlova);
        $this->book($ivanovaWeekly['2026-12-09'], $lebedeva);
        $this->book($smirnovaOther['2026-12-09'], $novikov);
        $this->book($petrovWeekly['2026-12-09'], $volkova);
        $this->book($ivanovaWeekly['2027-03-17'], $sokolov);
        $this->book($ivanovaWeekly['2027-06-16'], $belova);
        $this->book($smirnovaLab['2027-06-16'], $sokolov);
    }

    /**
     * Weekly or biweekly slots from $startDay through the series end cap.
     * Step is 7 or 14 days. The last day is kept only when it lands on the step.
     *
     * @return list<Slot>
     */
    private function series(
        TeacherProfile $teacher,
        string $startDay,
        int $stepDays,
        int $startHour,
        int $startMinute,
        int $endHour,
        int $endMinute,
        ?string $building,
        ?string $room,
        ?string $comment,
        ?string $url,
    ): array {
        $seriesId = (string) Str::uuid();
        $cursor = Carbon::parse($startDay, 'Asia/Yekaterinburg')->startOfDay();
        $last = Carbon::parse(Term::WORK_ENDS_ON, 'Asia/Yekaterinburg')->startOfDay();
        $created = [];

        while ($cursor->lte($last)) {
            $created[] = $this->once(
                $teacher,
                $cursor->toDateString(),
                $startHour,
                $startMinute,
                $endHour,
                $endMinute,
                $building,
                $room,
                $comment,
                $url,
                $seriesId,
                $stepDays === 14 ? 2 : 1,
            );
            $cursor->addDays($stepDays);
        }

        return $created;
    }

    private function once(
        TeacherProfile $teacher,
        string $day,
        int $startHour,
        int $startMinute,
        int $endHour,
        int $endMinute,
        ?string $building,
        ?string $room,
        ?string $comment,
        ?string $url,
        ?string $seriesId = null,
        ?int $seriesWeeks = null,
    ): Slot {
        return $teacher->slots()->create([
            'starts_at' => $this->at($day, $startHour, $startMinute),
            'ends_at' => $this->at($day, $endHour, $endMinute),
            'building' => $building,
            'room' => $room,
            'comment' => $comment,
            'meeting_url' => $url,
            'series_id' => $seriesId,
            'series_weeks' => $seriesId === null ? null : $seriesWeeks,
            'is_published' => true,
        ]);
    }

    private function book(Slot $slot, User $student, string $status = Booking::BOOKED): void
    {
        Booking::query()->create([
            'slot_id' => $slot->id,
            'student_profile_id' => $student->studentProfile->id,
            'status' => $status,
            'completed_at' => $status === Booking::COMPLETED ? $slot->ends_at : null,
        ]);
    }

    /**
     * @param  list<Slot>  $slots
     * @return array<string, Slot>
     */
    private function byDay(array $slots): array
    {
        $indexed = [];

        foreach ($slots as $slot) {
            $indexed[$slot->starts_at->format('Y-m-d')] = $slot;
        }

        return $indexed;
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
