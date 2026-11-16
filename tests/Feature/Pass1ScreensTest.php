<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use App\Support\Ru;
use Tests\ConsultationTestCase;

class Pass1ScreensTest extends ConsultationTestCase
{
    public function test_student_cancel_names_the_booking_and_keeps_finished_rows_hidden(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $teacher = $this->teacherUser('Иванова А. С.');
        $other = $this->studentUser('Белова Анна');

        $room = $this->futureSlot($teacher, '11:00', '11:45', 'Главный А', '314');
        $room->update(['comment' => 'принести черновик']);
        $online = $this->futureSlot($teacher, '16:00', '16:45');
        $online->update([
            'building' => null,
            'room' => null,
            'meeting_url' => 'https://meet.example.com/ivanova',
        ]);
        $done = $this->futureSlot($teacher, '09:00', '09:45', 'В', '12');
        $done->update([
            'starts_at' => now()->subDays(2)->setTime(9, 0),
            'ends_at' => now()->subDays(2)->setTime(9, 45),
            'conducted_at' => now()->subDays(2)->setTime(9, 45),
        ]);

        $this->actingAs($student)->post(route('bookings.store', $room))->assertRedirect();
        $this->actingAs($other)->post(route('bookings.store', $room))->assertRedirect();
        $this->actingAs($student)->post(route('bookings.store', $online))->assertRedirect();
        $done->bookings()->create([
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::COMPLETED,
            'completed_at' => $done->ends_at,
        ]);

        $booking = Booking::query()->where('slot_id', $room->id)->where('student_profile_id', $student->studentProfile->id)->firstOrFail();
        $onlineBooking = Booking::query()->where('slot_id', $online->id)->firstOrFail();

        $list = $this->actingAs($student)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('Показать завершённые')
            ->getContent();

        $open = $this->cardHtml($list, 'ауд. 314');
        $this->assertStringNotContainsString('data-finished', $open);
        $this->assertStringContainsString('>Преподаватель<', $open);
        $this->assertStringContainsString('Иванова А. С.', $open);
        $this->assertStringContainsString('>К слоту<', $open);
        $this->assertStringContainsString('принести черновик', $open);
        $this->assertStringContainsString('class="btn btn-small btn-ghost btn-quiet"', $open);
        $this->assertStringContainsString('aria-label="Отменить запись: Иванова А. С., '.$room->dateLabel().'"', $open);

        $finished = $this->cardHtml($list, 'ауд. 12');
        $this->assertStringContainsString('data-finished', $finished);
        $this->assertStringContainsString('hidden', $finished);
        $this->assertStringNotContainsString('>Отменить</a>', $finished);

        $page = $this->actingAs($student)
            ->get(route('bookings.cancel', $booking))
            ->assertOk()
            ->assertSee('Иванова А. С.')
            ->assertSee($room->dateLabel())
            ->assertSee($room->timeRange())
            ->assertSee('Главный А, ауд. 314')
            ->assertSee('Отмена сохраняется. Слот для остальных студентов остаётся открытым.')
            ->assertSee('Не отменять')
            ->assertSee('Отменить запись')
            ->getContent();
        $this->assertStringContainsString('href="'.route('bookings.index').'"', $page);
        $this->assertStringContainsString('class="btn btn-danger"', $page);
        $this->assertSame(Booking::BOOKED, $booking->refresh()->status);

        $onlinePage = $this->actingAs($student)
            ->get(route('bookings.cancel', $onlineBooking))
            ->assertOk()
            ->assertSee('https://meet.example.com/ivanova')
            ->assertDontSee('ауд.')
            ->getContent();
        $this->assertStringNotContainsString('Место', $this->whereHtml($onlinePage, 'https://meet.example.com/ivanova'));

        $this->actingAs($student)
            ->post(route('bookings.cancel.store', $booking))
            ->assertRedirect(route('bookings.index'));

        $this->assertSame(Booking::CANCELLED_BY_STUDENT, $booking->refresh()->status);
        $this->assertSame(Booking::BOOKED, Booking::query()->where('slot_id', $room->id)->where('student_profile_id', $other->studentProfile->id)->firstOrFail()->status);
        $this->assertTrue($room->refresh()->is_published);
    }

    public function test_teacher_cancel_names_one_student_and_returns_to_the_roster(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $sokolov = $this->studentUser('Соколов Илья', 'ПИ-21');
        $belova = $this->studentUser('Белова Анна', 'ПИ-21');
        $slot = $this->futureSlot($teacher, '11:00', '11:45', 'Главный А', '314');

        $this->actingAs($sokolov)->post(route('bookings.store', $slot))->assertRedirect();
        $this->actingAs($belova)->post(route('bookings.store', $slot))->assertRedirect();

        $sokolovBooking = Booking::query()->where('student_profile_id', $sokolov->studentProfile->id)->firstOrFail();
        $belovaBooking = Booking::query()->where('student_profile_id', $belova->studentProfile->id)->firstOrFail();

        $roster = $this->actingAs($teacher)
            ->get(route('slots.roster', $slot))
            ->assertOk()
            ->getContent();
        $card = $this->rosterCard($roster, 'Соколов Илья');
        $this->assertStringContainsString('>Группа<', $card);
        $this->assertStringContainsString('>Студент<', $card);
        $this->assertStringContainsString('btn-quiet', $card);
        $this->assertStringContainsString('aria-label="Отменить запись: Соколов Илья, ПИ-21"', $card);

        $page = $this->actingAs($teacher)
            ->get(route('teacher.bookings.cancel', $sokolovBooking))
            ->assertOk()
            ->assertSee('Соколов Илья, ПИ-21')
            ->assertSee($slot->dateLabel())
            ->assertSee('Главный А, ауд. 314')
            ->assertSee('Комментарий студенту, необязательно')
            ->assertSee('Студент увидит его в «Моих записях».')
            ->assertSee('Отменяется запись одного студента. Остальные остаются, новая запись на слот возможна.')
            ->assertSee('Не отменять')
            ->getContent();
        $this->assertStringContainsString('href="'.route('slots.roster', $slot).'"', $page);
        $this->assertStringNotContainsString('К моим слотам', $page);
        $this->assertSame(Booking::BOOKED, $sokolovBooking->refresh()->status);

        $this->actingAs($teacher)
            ->post(route('teacher.bookings.cancel.store', $sokolovBooking), [
                'comment' => 'не смогу быть на кафедре',
            ])
            ->assertRedirect(route('slots.roster', $slot))
            ->assertSessionHas('status', 'Запись студента отменена. Слот для остальных открыт.');

        $this->assertSame(Booking::CANCELLED_BY_TEACHER, $sokolovBooking->refresh()->status);
        $this->assertSame('не смогу быть на кафедре', $sokolovBooking->teacher_comment);
        $this->assertSame(Booking::BOOKED, $belovaBooking->refresh()->status);
        $this->assertTrue($slot->refresh()->is_published);

        $after = $this->actingAs($teacher)
            ->get(route('slots.roster', $slot))
            ->assertOk()
            ->assertSee('не смогу быть на кафедре')
            ->assertSee('Запись студента отменена. Слот для остальных открыт.')
            ->getContent();
        $cancelled = $this->rosterCard($after, 'Соколов Илья');
        $this->assertStringContainsString('>Комментарий<', $cancelled);
        $this->assertStringNotContainsString('>Отменить</a>', $cancelled);
        $this->assertStringContainsString('>Отменить</a>', $this->rosterCard($after, 'Белова Анна'));

        $mine = $this->actingAs($sokolov)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('От преподавателя')
            ->assertSee('не смогу быть на кафедре')
            ->getContent();
        $this->assertStringContainsString('>От преподавателя<', $this->cardHtml($mine, 'не смогу быть на кафедре'));
    }

    public function test_slot_form_explains_format_and_puts_the_fix_next_to_the_field(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $day = now()->addDays(4)->toDateString();

        $create = $this->actingAs($teacher)
            ->get(route('teacher.slots.create'))
            ->assertOk()
            ->assertSee('Очно: корпус из списка и аудитория. Онлайн: ссылка http:// или https://, без корпуса и аудитории.')
            ->assertSee('Ссылка на встречу, необязательно')
            ->assertSee('Последний день повтора')
            ->assertSee('Учитывается, только если включено «Повторять каждую неделю». Не позже 19.04.2027.')
            ->assertSee('Укажите дату.')
            ->assertSee('Выберите корпус из списка.')
            ->getContent();
        $this->assertStringNotContainsString('>Ссылка<', $create);
        $this->assertStringNotContainsString('Ссылка, необязательно', $create);
        $this->assertDoesNotMatchRegularExpression('/id="repeat_until"[^>]*disabled/', $create);

        $room = $this->followingRedirects()
            ->actingAs($teacher)
            ->from(route('teacher.slots.create'))
            ->post(route('teacher.slots.store'), [
                'format' => 'in_person',
                'date' => $day,
                'start_time' => '10:00',
                'end_time' => '10:45',
                'building' => '',
                'room' => str_repeat('А', 33),
            ])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="building"[^>]*aria-invalid="true"[^>]*>\s*<option[^>]*>Выберите корпус<\/option>[\s\S]*?<p class="error" id="building-error">Выберите корпус из списка\.<\/p>/',
            $room,
        );
        $this->assertMatchesRegularExpression(
            '/id="room"[^>]*aria-invalid="true"[^>]*aria-describedby="room-error"[^>]*>\s*<p class="error" id="room-error">Аудитория не длиннее 32 символов\.<\/p>/',
            $room,
        );
        $this->assertStringNotContainsString('class="notice error"', $room);
        $this->assertStringNotContainsString('characters', $room);
        $this->assertSame(0, Slot::query()->count());

        $short = $this->followingRedirects()
            ->actingAs($teacher)
            ->from(route('teacher.slots.create'))
            ->post(route('teacher.slots.store'), [
                'format' => 'in_person',
                'date' => $day,
                'start_time' => '10:00',
                'end_time' => '10:10',
                'building' => 'А',
                'room' => '12',
            ])
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/id="end_time"[^>]*>\s*<p class="error" id="end_time-error">Конец позже начала, длительность не короче 15 минут\.<\/p>/',
            $short,
        );
        $this->assertStringNotContainsString('class="notice error"', $short);

        $link = $this->followingRedirects()
            ->actingAs($teacher)
            ->from(route('teacher.slots.create'))
            ->post(route('teacher.slots.store'), [
                'format' => 'online',
                'date' => $day,
                'start_time' => '12:00',
                'end_time' => '12:45',
                'building' => 'Главный А',
                'room' => '220',
                'meeting_url' => 'https://meet.example.com/room one',
            ])
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/id="meeting_url"[^>]*aria-invalid="true"[^>]*>\s*<p class="error" id="meeting_url-error">Ссылка должна быть адресом http:\/\/ или https:\/\/ без пробелов\.<\/p>/',
            $link,
        );
        $this->assertMatchesRegularExpression('/data-in-person[^>]*hidden/', $link);
        $this->assertStringContainsString('Ссылка на встречу', $link);
        $this->assertDoesNotMatchRegularExpression('/id="(?:room|building)-error"/', $link);
        $this->assertStringNotContainsString('class="notice error"', $link);
        $this->assertSame(0, Slot::query()->count());

        $until = $this->followingRedirects()
            ->actingAs($teacher)
            ->from(route('teacher.slots.create'))
            ->post(route('teacher.slots.store'), [
                'format' => 'in_person',
                'date' => $day,
                'start_time' => '15:00',
                'end_time' => '15:45',
                'building' => 'Б',
                'room' => '8',
                'repeat_weekly' => '1',
                'repeat_until' => now()->addDay()->format('d.m.Y'),
            ])
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/id="repeat_until"[^>]*>\s*<p class="error" id="repeat_until-error">Дата окончания не раньше даты слота\.<\/p>/',
            $until,
        );
        $this->assertStringContainsString('Последний день серии, не позже 19.04.2027.', $until);
        $this->assertStringNotContainsString('class="notice error"', $until);

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'online',
            'date' => $day,
            'start_time' => '18:00',
            'end_time' => '18:45',
            'meeting_url' => 'https://meet.example.com/weekly',
            'repeat_weekly' => '1',
            'repeat_until' => '10.11.2026',
        ])->assertRedirect(route('teacher.slots.index'));

        $anchor = Slot::query()->orderBy('starts_at')->firstOrFail();
        $later = Slot::query()->where('series_id', $anchor->series_id)->whereKeyNot($anchor->id)->firstOrFail();
        $this->assertNull($anchor->building);
        $this->assertNull($anchor->room);
        $this->assertSame('https://meet.example.com/weekly', $anchor->meeting_url);

        $edit = $this->actingAs($teacher)
            ->get(route('teacher.slots.edit', $anchor))
            ->assertOk()
            ->assertSee('Ссылка на встречу')
            ->getContent();
        $this->assertMatchesRegularExpression('/<label for="meeting_url">Ссылка на встречу<\/label>/', $edit);
        $this->assertDoesNotMatchRegularExpression('/<label for="meeting_url">Ссылка на встречу, необязательно<\/label>/', $edit);
        $this->assertMatchesRegularExpression('/data-in-person[^>]*hidden/', $edit);
        $this->assertStringContainsString(Ru::seriesApplyHint($anchor->laterSeriesDays()->count()), $edit);
        $this->assertDoesNotMatchRegularExpression(
            '/id="series-apply-note"[^>]*>\s*Будут изменены/',
            $edit,
        );

        $invalid = $this->followingRedirects()
            ->actingAs($teacher)
            ->from(route('teacher.slots.edit', $anchor))
            ->post(route('teacher.slots.update', $anchor), [
                'format' => 'online',
                'date' => $anchor->starts_at->toDateString(),
                'start_time' => $anchor->starts_at->format('H:i'),
                'end_time' => $anchor->ends_at->format('H:i'),
                'meeting_url' => 'ftp://files.example.com/room',
                'apply_to_series' => '1',
            ])
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/id="meeting_url"[^>]*>\s*<p class="error" id="meeting_url-error">Ссылка должна быть адресом http:\/\/ или https:\/\/ без пробелов\.<\/p>/',
            $invalid,
        );
        $this->assertMatchesRegularExpression(
            '/id="series-apply-note"[^>]*>\s*'.preg_quote(Ru::seriesDaysWillChange($anchor->laterSeriesDays()->count()), '/').'<\/p>/',
            $invalid,
        );
        $this->assertSame('https://meet.example.com/weekly', $anchor->refresh()->meeting_url);
        $this->assertSame('https://meet.example.com/weekly', $later->refresh()->meeting_url);

        $this->actingAs($teacher)->post(route('teacher.slots.update', $anchor), [
            'format' => 'in_person',
            'date' => $anchor->starts_at->toDateString(),
            'start_time' => $anchor->starts_at->format('H:i'),
            'end_time' => $anchor->ends_at->format('H:i'),
            'building' => 'В',
            'room' => '105',
            'comment' => 'только этот день',
        ])->assertRedirect(route('teacher.slots.index'));

        $this->assertSame('В', $anchor->refresh()->building);
        $this->assertSame('105', $anchor->room);
        $this->assertNull($anchor->meeting_url);
        $this->assertSame('только этот день', $anchor->comment);
        $this->assertNull($later->refresh()->building);
        $this->assertSame('https://meet.example.com/weekly', $later->meeting_url);
    }

    private function cardHtml(string $html, string $marker): string
    {
        $pattern = '/<tr class="[^"]*booking-card"[^>]*>(?:(?!<\/tr>).)*'.preg_quote($marker, '/').'(?:(?!<\/tr>).)*<\/tr>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }

    private function rosterCard(string $html, string $marker): string
    {
        $pattern = '/<tr class="roster-card">(?:(?!<\/tr>).)*'.preg_quote($marker, '/').'(?:(?!<\/tr>).)*<\/tr>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }

    private function whereHtml(string $html, string $marker): string
    {
        $pattern = '/<div class="where">(?:(?!<\/div>).)*'.preg_quote($marker, '/').'(?:(?!<\/div>).)*<\/div>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }
}
