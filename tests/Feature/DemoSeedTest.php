<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\ConsultationTestCase;

class DemoSeedTest extends ConsultationTestCase
{
    public function test_demo_consultations_sit_at_the_end_of_spring_2027(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-05-20 09:00:00'));
        $this->seed();

        $ivanova = User::query()->where('email', 'ivanova@kafedra.local')->firstOrFail();
        $petrov = User::query()->where('email', 'petrov@kafedra.local')->firstOrFail();
        $smirnova = User::query()->where('email', 'smirnova@kafedra.local')->firstOrFail();
        $sokolov = User::query()->where('email', 'sokolov@kafedra.local')->firstOrFail();

        $this->assertTrue(Hash::check(DatabaseSeeder::PASSWORD, $sokolov->password));
        $this->assertTrue(Hash::check(DatabaseSeeder::PASSWORD, $ivanova->password));
        $this->assertSame('Parol-123', DatabaseSeeder::PASSWORD);

        $this->assertSame(0, Slot::query()->where('starts_at', '<', '2027-05-01')->count());
        $this->assertSame(8, Slot::query()->count());
        $this->assertSame(1, Slot::query()->whereNotNull('conducted_at')->count());

        $conducted = $this->teacherSlot($ivanova, '2027-05-04 10:00');
        $this->assertSame('10:45', $conducted->ends_at->format('H:i'));
        $this->assertTrue($conducted->conducted_at->equalTo($conducted->ends_at));
        $this->assertSame([Booking::COMPLETED], $conducted->bookings->pluck('status')->all());
        $this->assertSame(['Соколов Илья'], $this->studentNames($conducted));

        $series = Slot::query()->whereNotNull('series_id')->orderBy('starts_at')->get();
        $this->assertCount(2, $series);
        $this->assertCount(1, $series->pluck('series_id')->unique());
        $this->assertSame($ivanova->teacherProfile->id, $series[0]->teacher_profile_id);
        $this->assertSame('2027-05-25 10:00', $series[0]->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2027-06-01 10:00', $series[1]->starts_at->format('Y-m-d H:i'));
        $this->assertSame('10:45', $series[0]->ends_at->format('H:i'));
        $this->assertSame('10:45', $series[1]->ends_at->format('H:i'));
        $this->assertSame(['Соколов Илья'], $this->studentNames($series[0]));
        $this->assertSame(Booking::BOOKED, $series[0]->bookings->first()->status);
        $this->assertTrue($series[1]->bookings->isEmpty());

        $online = Slot::query()->whereNotNull('meeting_url')->get();
        $this->assertCount(1, $online);
        $this->assertSame('2027-05-25 16:00', $online->first()->starts_at->format('Y-m-d H:i'));
        $this->assertSame('16:45', $online->first()->ends_at->format('H:i'));
        $this->assertSame('https://meet.example.com/ivanova', $online->first()->meeting_url);
        $this->assertTrue($online->first()->isOnline());
        $this->assertTrue($online->first()->bookings->isEmpty());
        $this->assertSame($ivanova->teacherProfile->id, $online->first()->teacher_profile_id);

        $draft = $this->teacherSlot($ivanova, '2027-05-26 10:00');
        $this->assertSame('принести черновик курсовой', $draft->comment);
        $this->assertEqualsCanonicalizing(['Орлова Марина', 'Лебедева Ольга'], $this->studentNames($draft));

        $lab = $this->teacherSlot($smirnova, '2027-05-27 15:00');
        $this->assertSame('разбор лабораторной', $lab->comment);
        $this->assertEqualsCanonicalizing(['Волкова Дарья', 'Новиков Игорь'], $this->studentNames($lab));
        $this->assertNull($lab->series_id);
        $this->assertNull($lab->meeting_url);

        $group = $this->teacherSlot($ivanova, '2027-05-28 11:00');
        $this->assertEqualsCanonicalizing(
            ['Белова Анна', 'Соколов Илья', 'Морозов Кирилл'],
            $this->studentNames($group),
        );

        $free = $this->teacherSlot($petrov, '2027-05-29 12:00');
        $this->assertSame('12:45', $free->ends_at->format('H:i'));
        $this->assertSame('Б', $free->building);
        $this->assertSame('210', $free->room);
        $this->assertTrue($free->bookings->isEmpty());
        $this->assertNull($free->series_id);
        $this->assertNull($free->meeting_url);

        $span = ['from' => '2027-05-04', 'to' => '2027-06-01'];

        $home = $this->actingAs($sokolov)->get(route('home', $span))->assertOk();
        $home->assertSee('Показать завершённые')
            ->assertSee('04.05.2027')
            ->assertSee('25.05.2027')
            ->assertSee('26.05.2027')
            ->assertSee('27.05.2027')
            ->assertSee('28.05.2027')
            ->assertSee('01.06.2027')
            ->assertSee('https://meet.example.com/ivanova')
            ->assertSee('Записаться');

        $this->actingAs($sokolov)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('Показать завершённые')
            ->assertSee('04.05.2027')
            ->assertSee('25.05.2027')
            ->assertSee('28.05.2027');

        $this->actingAs($ivanova)
            ->get(route('teacher.slots.index', $span))
            ->assertOk()
            ->assertSee('Показать завершённые')
            ->assertSee('еженедельно')
            ->assertSee('25.05.2027')
            ->assertSee('01.06.2027')
            ->assertSee('04.05.2027');

        $this->actingAs($petrov)
            ->get(route('teacher.slots.index', $span))
            ->assertOk()
            ->assertSee('29.05.2027')
            ->assertSee('свободен')
            ->assertDontSee('Показать завершённые');
    }

    private function teacherSlot(User $teacher, string $start): Slot
    {
        return Slot::query()
            ->with('bookings.studentProfile.user')
            ->where('teacher_profile_id', $teacher->teacherProfile->id)
            ->where('starts_at', $start.':00')
            ->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function studentNames(Slot $slot): array
    {
        return $slot->bookings
            ->map(fn (Booking $booking) => $booking->studentProfile->user->name)
            ->values()
            ->all();
    }
}
