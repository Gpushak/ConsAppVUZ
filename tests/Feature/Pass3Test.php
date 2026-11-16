<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use App\Support\Ru;
use Tests\ConsultationTestCase;

class Pass3Test extends ConsultationTestCase
{
    public function test_apply_copies_place_to_later_days_and_leaves_booked_time_and_past_days(): void
    {
        $this->assertSame('Будет изменён ещё 1 день серии.', Ru::seriesDaysWillChange(1));
        $this->assertSame('Будут изменены ещё 2 дня серии.', Ru::seriesDaysWillChange(2));
        $this->assertSame('Будут изменены ещё 5 дней серии.', Ru::seriesDaysWillChange(5));
        $this->assertSame('Оставшиеся дни серии не изменятся.', Ru::seriesDaysWillChange(0));

        $teacher = $this->teacherUser('Иванова А. С.');
        $student = $this->studentUser('Соколов Илья');
        $anchor = $this->weeklySeries($teacher, '101', '17.11.2026');
        $seriesId = $anchor->series_id;

        $past = $this->seriesDay($teacher, $seriesId, '2026-10-20', '101');
        $earlier = $this->seriesDay($teacher, $seriesId, '2026-10-26', '101');
        $booked = Slot::query()->where('series_id', $seriesId)->whereDate('starts_at', '2026-11-03')->firstOrFail();
        $free = Slot::query()->where('series_id', $seriesId)->whereDate('starts_at', '2026-11-10')->firstOrFail();
        $conducted = Slot::query()->where('series_id', $seriesId)->whereDate('starts_at', '2026-11-17')->firstOrFail();
        $conducted->update(['conducted_at' => now()]);
        $this->actingAs($student)->post(route('bookings.store', $booked))->assertRedirect();

        $alone = $this->futureSlot($teacher, '18:00', '18:45', 'Г', '7');
        $this->actingAs($teacher)
            ->get(route('teacher.slots.edit', $alone))
            ->assertOk()
            ->assertDontSee('Применить к оставшимся дням серии');

        $form = $this->actingAs($teacher)
            ->get(route('teacher.slots.edit', $anchor))
            ->assertOk()
            ->assertSee('Применить к оставшимся дням серии', false)
            ->assertSee('Будут изменены ещё 2 дня серии.', false)
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/id="apply_to_series"[^>]*>\s*Применить к оставшимся дням серии\s*<\/label>\s*<p class="field-note" id="series-apply-note"[^>]*data-on="Будут изменены ещё 2 дня серии\."[^>]*>Изменится только этот день\. Отметьте, чтобы изменить ещё 2 дня серии\.<\/p>/',
            $form,
        );

        $this->actingAs($teacher)
            ->post(route('teacher.slots.update', $anchor), $this->editPayload($anchor, [
                'comment' => 'только этот день',
            ]))
            ->assertRedirect(route('teacher.slots.index'))
            ->assertSessionHas('status', 'Слот изменён.');

        $this->assertSame('только этот день', $anchor->refresh()->comment);
        $this->assertSame('черновик', $booked->refresh()->comment);
        $this->assertSame('черновик', $free->refresh()->comment);
        $this->assertSame('черновик', $past->refresh()->comment);

        $this->actingAs($teacher)
            ->post(route('teacher.slots.update', $anchor), $this->editPayload($anchor, [
                'start_time' => '14:00',
                'end_time' => '14:45',
                'building' => 'Б',
                'room' => '220',
                'comment' => 'новое место',
                'meeting_url' => 'https://meet.example.com/series',
                'apply_to_series' => '1',
            ]))
            ->assertRedirect(route('teacher.slots.index'))
            ->assertSessionHas('status', 'Слот изменён. Будут изменены ещё 2 дня серии.');

        $anchor->refresh();
        $this->assertSame('2026-10-27 14:00', $anchor->starts_at->format('Y-m-d H:i'));
        $this->assertSame('14:45', $anchor->ends_at->format('H:i'));
        $this->assertSame('Б', $anchor->building);
        $this->assertSame('220', $anchor->room);
        $this->assertSame('новое место', $anchor->comment);
        $this->assertSame('https://meet.example.com/series', $anchor->meeting_url);

        $booked->refresh();
        $this->assertSame('2026-11-03 10:00', $booked->starts_at->format('Y-m-d H:i'));
        $this->assertSame('10:45', $booked->ends_at->format('H:i'));
        $this->assertSame('Б', $booked->building);
        $this->assertSame('220', $booked->room);
        $this->assertSame('новое место', $booked->comment);
        $this->assertSame('https://meet.example.com/series', $booked->meeting_url);

        $free->refresh();
        $this->assertSame('2026-11-10 14:00', $free->starts_at->format('Y-m-d H:i'));
        $this->assertSame('14:45', $free->ends_at->format('H:i'));
        $this->assertSame('220', $free->room);
        $this->assertSame('новое место', $free->comment);
        $this->assertSame('https://meet.example.com/series', $free->meeting_url);

        $past->refresh();
        $this->assertSame('2026-10-20 10:00', $past->starts_at->format('Y-m-d H:i'));
        $this->assertSame('10:45', $past->ends_at->format('H:i'));
        $this->assertSame('А', $past->building);
        $this->assertSame('101', $past->room);
        $this->assertSame('черновик', $past->comment);
        $this->assertNull($past->meeting_url);

        $earlier->refresh();
        $this->assertSame('2026-10-26 10:00', $earlier->starts_at->format('Y-m-d H:i'));
        $this->assertSame('101', $earlier->room);
        $this->assertSame('черновик', $earlier->comment);
        $this->assertNull($earlier->meeting_url);

        $conducted->refresh();
        $this->assertSame('2026-11-17 10:00', $conducted->starts_at->format('Y-m-d H:i'));
        $this->assertSame('101', $conducted->room);
        $this->assertSame('черновик', $conducted->comment);
        $this->assertNull($conducted->meeting_url);
        $this->assertNotNull($conducted->conducted_at);
    }

    public function test_overlap_on_a_later_day_skips_that_day_and_saves_the_rest(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $anchor = $this->weeklySeries($teacher, '55', '10.11.2026');
        $seriesId = $anchor->series_id;
        $next = Slot::query()->where('series_id', $seriesId)->whereDate('starts_at', '2026-11-03')->firstOrFail();
        $blockedDay = Slot::query()->where('series_id', $seriesId)->whereDate('starts_at', '2026-11-10')->firstOrFail();

        $blocker = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => '2026-10-27 14:00:00',
            'ends_at' => '2026-10-27 14:45:00',
            'building' => 'А',
            'room' => '9',
            'is_published' => true,
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.slots.update', $anchor), $this->editPayload($anchor, [
                'start_time' => '14:00',
                'end_time' => '14:45',
                'room' => '220',
                'apply_to_series' => '1',
            ]))
            ->assertSessionHasErrors([
                'start_time' => 'Это время пересекается с другим вашим слотом.',
            ]);

        $this->assertSame('10:00', $anchor->refresh()->starts_at->format('H:i'));
        $this->assertSame('55', $anchor->room);
        $this->assertSame('55', $next->refresh()->room);
        $this->assertSame('10:00', $next->starts_at->format('H:i'));

        $blocker->update([
            'starts_at' => '2026-11-10 14:00:00',
            'ends_at' => '2026-11-10 14:45:00',
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.slots.update', $anchor), $this->editPayload($anchor, [
                'start_time' => '14:00',
                'end_time' => '14:45',
                'building' => 'Б',
                'room' => '220',
                'comment' => 'кроме пересечения',
                'meeting_url' => 'https://meet.example.com/skip',
                'apply_to_series' => '1',
            ]))
            ->assertRedirect(route('teacher.slots.index'))
            ->assertSessionHas('status', 'Слот изменён. Будет изменён ещё 1 день серии. Дни с пересечением пропущены.');

        $anchor->refresh();
        $this->assertSame('2026-10-27 14:00', $anchor->starts_at->format('Y-m-d H:i'));
        $this->assertSame('220', $anchor->room);

        $next->refresh();
        $this->assertSame('2026-11-03 14:00', $next->starts_at->format('Y-m-d H:i'));
        $this->assertSame('14:45', $next->ends_at->format('H:i'));
        $this->assertSame('220', $next->room);
        $this->assertSame('кроме пересечения', $next->comment);
        $this->assertSame('https://meet.example.com/skip', $next->meeting_url);

        $blockedDay->refresh();
        $this->assertSame('2026-11-10 10:00', $blockedDay->starts_at->format('Y-m-d H:i'));
        $this->assertSame('10:45', $blockedDay->ends_at->format('H:i'));
        $this->assertSame('А', $blockedDay->building);
        $this->assertSame('55', $blockedDay->room);
        $this->assertSame('черновик', $blockedDay->comment);
        $this->assertNull($blockedDay->meeting_url);
    }

    public function test_my_bookings_are_cards_on_a_phone(): void
    {
        $css = file_get_contents(public_path('css/app.css'));
        $this->assertIsString($css);
        $phone = $this->phoneCss($css);
        $before = substr($css, 0, (int) strpos($css, '@media (max-width: 760px)'));

        $this->assertStringNotContainsString('booking-extra', $before);
        $this->assertLessThan(
            strpos($phone, '.booking-cards table'),
            strpos($phone, 'width: max-content'),
        );
        $this->assertMatchesRegularExpression(
            '/\.table-wrap\.booking-cards\s*\{[^}]*overflow-x:\s*clip/s',
            $phone,
        );
        $this->assertMatchesRegularExpression(
            '/\.booking-cards table,\s*\.booking-cards tbody\s*\{[^}]*display:\s*block;[^}]*width:\s*100%;[^}]*min-width:\s*0;/s',
            $phone,
        );
        $this->assertMatchesRegularExpression(
            '/\.booking-cards thead\s*\{[^}]*display:\s*none/s',
            $phone,
        );
        $this->assertMatchesRegularExpression(
            '/\.booking-cards tr\.booking-card\s*\{[^}]*flex-direction:\s*column/s',
            $phone,
        );
        $this->assertMatchesRegularExpression(
            '/\.booking-cards tr\.booking-card > td\.booking-who,\s*\.booking-cards tr\.booking-card > td\.booking-note\s*\{[^}]*display:\s*block/s',
            $phone,
        );
        $this->assertMatchesRegularExpression(
            '/\.booking-cards \.place-link\s*\{[^}]*overflow-wrap:\s*anywhere;[^}]*word-break:\s*break-all/s',
            $phone,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.booking-cards table[^{]*\{[^}]*width:\s*max-content/s',
            $phone,
        );

        $student = $this->studentUser('Соколов Илья');
        $teacher = $this->teacherUser('Иванова А. С.');
        $longUrl = 'https://meet.example.com/'.str_repeat('very-long-meeting-room-', 6).'end';

        $place = $this->futureSlot($teacher, '11:00', '11:45', 'Главный А', '314');
        $place->update(['comment' => 'принести черновик']);
        $online = $this->futureSlot($teacher, '16:00', '16:45');
        $online->update([
            'building' => null,
            'room' => null,
            'meeting_url' => $longUrl,
            'comment' => 'подключиться за пять минут',
        ]);
        $done = $this->futureSlot($teacher, '09:00', '09:45', 'В', '12');
        $done->update([
            'starts_at' => now()->subDays(2)->setTime(9, 0),
            'ends_at' => now()->subDays(2)->setTime(9, 45),
            'conducted_at' => now()->subDays(2)->setTime(9, 45),
        ]);

        $this->actingAs($student)->post(route('bookings.store', $place))->assertRedirect();
        $this->actingAs($student)->post(route('bookings.store', $online))->assertRedirect();
        $done->bookings()->create([
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::COMPLETED,
            'completed_at' => $done->ends_at,
        ]);

        $html = $this->actingAs($student)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('booking-cards', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, '<table'));

        $roomCard = $this->cardHtml($html, 'ауд. 314');
        $this->assertStringContainsString('booking-date', $roomCard);
        $this->assertStringContainsString('booking-time', $roomCard);
        $this->assertStringContainsString($place->dateLabel(), $roomCard);
        $this->assertStringContainsString($place->timeRange(), $roomCard);
        $this->assertStringContainsString('Главный А, ауд. 314', $roomCard);
        $this->assertStringContainsString('booking-status', $roomCard);
        $this->assertStringContainsString('занят', $roomCard);
        $this->assertStringContainsString('booking-who', $roomCard);
        $this->assertStringContainsString('>Преподаватель<', $roomCard);
        $this->assertStringContainsString('booking-note', $roomCard);
        $this->assertStringContainsString('>К слоту<', $roomCard);
        $this->assertStringContainsString('Иванова А. С.', $roomCard);
        $this->assertStringContainsString('принести черновик', $roomCard);
        $this->assertSame(1, substr_count($roomCard, 'class="btn'));
        $this->assertStringContainsString('>Отменить</a>', $roomCard);
        $this->assertStringNotContainsString('<table', $roomCard);

        $linkCard = $this->cardHtml($html, 'very-long-meeting-room-');
        $this->assertStringContainsString('class="place-link"', $linkCard);
        $this->assertStringContainsString($longUrl, $linkCard);
        $this->assertStringContainsString('booking-time', $linkCard);
        $this->assertStringContainsString('занят', $linkCard);
        $this->assertSame(1, substr_count($linkCard, 'class="btn'));
        $this->assertStringContainsString('>Отменить</a>', $linkCard);
        $this->assertStringNotContainsString('ауд.', $linkCard);

        $doneCard = $this->cardHtml($html, 'ауд. 12');
        $this->assertStringContainsString('data-finished', $doneCard);
        $this->assertStringContainsString('проведён', $doneCard);
        $this->assertStringContainsString('booking-date', $doneCard);
        $this->assertStringNotContainsString('class="btn', $doneCard);
        $this->assertStringNotContainsString('>Отменить</a>', $doneCard);
    }

    private function weeklySeries(User $teacher, string $room, string $until): Slot
    {
        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => '27.10.2026',
            'start_time' => '10:00',
            'end_time' => '10:45',
            'building' => 'А',
            'room' => $room,
            'comment' => 'черновик',
            'repeat_weekly' => '1',
            'repeat_until' => $until,
        ])->assertRedirect(route('teacher.slots.index'));

        return Slot::query()->where('room', $room)->orderBy('starts_at')->firstOrFail();
    }

    private function seriesDay(User $teacher, string $seriesId, string $day, string $room): Slot
    {
        return Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'series_id' => $seriesId,
            'starts_at' => $day.' 10:00:00',
            'ends_at' => $day.' 10:45:00',
            'building' => 'А',
            'room' => $room,
            'comment' => 'черновик',
            'is_published' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function editPayload(Slot $slot, array $overrides = []): array
    {
        return array_merge([
            'format' => 'in_person',
            'date' => $slot->starts_at->toDateString(),
            'start_time' => $slot->starts_at->format('H:i'),
            'end_time' => $slot->ends_at->format('H:i'),
            'building' => $slot->building,
            'room' => $slot->room,
            'comment' => $slot->comment,
            'meeting_url' => $slot->meeting_url,
        ], $overrides);
    }

    private function phoneCss(string $css): string
    {
        $start = strpos($css, '@media (max-width: 760px)');
        $this->assertNotFalse($start);
        $open = strpos($css, '{', $start);
        $this->assertNotFalse($open);
        $depth = 0;
        $length = strlen($css);

        for ($i = $open; $i < $length; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($css, $start, $i - $start + 1);
                }
            }
        }

        $this->fail('Phone breakpoint CSS was not closed.');
    }

    private function cardHtml(string $html, string $marker): string
    {
        $pattern = '/<tr class="[^"]*booking-card"[^>]*>(?:(?!<\/tr>).)*'.preg_quote($marker, '/').'(?:(?!<\/tr>).)*<\/tr>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }
}
