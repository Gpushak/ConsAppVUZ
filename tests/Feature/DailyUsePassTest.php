<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use App\Support\Ru;
use Tests\ConsultationTestCase;

class DailyUsePassTest extends ConsultationTestCase
{
    public function test_place_and_meeting_link_are_labeled_on_confirm_and_bookings(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $teacher = $this->teacherUser('Иванова А. С.');
        $student->studentProfile->favoriteTeachers()->attach($teacher->teacherProfile->id);

        $inPerson = $this->futureSlot($teacher, '10:00', '10:45', 'В', '105');
        $inPerson->update(['meeting_url' => 'https://meet.example.com/lab', 'comment' => 'разбор лабораторной']);
        $online = $this->futureSlot($teacher, '16:00', '16:45');
        $online->update([
            'building' => null,
            'room' => null,
            'meeting_url' => 'https://meet.example.com/ivanova',
            'comment' => 'подключиться за пять минут',
        ]);

        $confirm = $this->actingAs($student)
            ->get(route('bookings.create', $inPerson))
            ->assertOk()
            ->assertSee('Подтверждение записи')
            ->assertDontSee('<table', false)
            ->getContent();

        $where = $this->whereHtml($confirm, 'https://meet.example.com/lab');
        $this->assertStringContainsString('Место', $where);
        $this->assertStringContainsString('В, ауд. 105', $where);
        $this->assertStringContainsString('Ссылка на встречу', $where);

        $onlineConfirm = $this->actingAs($student)
            ->get(route('bookings.create', $online))
            ->assertOk()
            ->assertDontSee('ауд.')
            ->getContent();
        $onlineWhere = $this->whereHtml($onlineConfirm, 'https://meet.example.com/ivanova');
        $this->assertStringContainsString('Ссылка на встречу', $onlineWhere);
        $this->assertStringNotContainsString('Место', $onlineWhere);

        $this->actingAs($student)->post(route('bookings.store', $inPerson))->assertRedirect(route('bookings.index'));
        $this->actingAs($student)->post(route('bookings.store', $online))->assertRedirect(route('bookings.index'));

        $list = $this->actingAs($student)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('slot-cards', false)
            ->getContent();

        $booked = $this->whereHtml($list, 'https://meet.example.com/lab');
        $this->assertStringContainsString('Место', $booked);
        $this->assertStringContainsString('В, ауд. 105', $booked);
        $this->assertStringContainsString('Ссылка на встречу', $booked);
        $this->assertStringContainsString('разбор лабораторной', $this->rowHtml($list, 'ауд. 105'));

        $booking = Booking::query()->where('slot_id', $online->id)->firstOrFail();
        $show = $this->actingAs($student)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('<table', false)
            ->assertDontSee('ауд.')
            ->getContent();
        $this->assertStringContainsString('Ссылка на встречу', $this->whereHtml($show, 'https://meet.example.com/ivanova'));
        $this->assertStringContainsString('подключиться за пять минут', $show);
    }

    public function test_series_shows_remaining_days_and_stop_counts_empty_ones(): void
    {
        $this->assertSame('впереди ещё 1 день', Ru::aheadDays(1));
        $this->assertSame('впереди ещё 2 дня', Ru::aheadDays(2));
        $this->assertSame('впереди ещё 4 дня', Ru::aheadDays(4));
        $this->assertSame('впереди ещё 5 дней', Ru::aheadDays(5));
        $this->assertSame('впереди ещё 11 дней', Ru::aheadDays(11));
        $this->assertSame('впереди ещё 21 день', Ru::aheadDays(21));
        $this->assertSame('Впереди ещё 4 дня.', Ru::aheadDaysSentence(4));
        $this->assertSame('Будет снят с публикации 1 пустой будущий день.', Ru::emptyDaysWillBeUnpublished(1));
        $this->assertSame('Будут сняты с публикации 3 пустых будущих дня.', Ru::emptyDaysWillBeUnpublished(3));
        $this->assertSame('Будут сняты с публикации 5 пустых будущих дней.', Ru::emptyDaysWillBeUnpublished(5));

        $teacher = $this->teacherUser('Иванова А. С.');
        $student = $this->studentUser('Соколов Илья');

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => '27.10.2026',
            'start_time' => '15:00',
            'end_time' => '15:45',
            'building' => 'А',
            'room' => '77',
            'repeat_weekly' => '1',
            'repeat_until' => '17.11.2026',
        ])->assertRedirect(route('teacher.slots.index'));

        $series = Slot::query()->where('room', '77')->orderBy('starts_at')->get();
        $this->assertCount(4, $series);
        $this->actingAs($student)->post(route('bookings.store', $series->first()))->assertRedirect();

        $html = $this->actingAs($teacher)
            ->get(route('teacher.slots.index'))
            ->assertOk()
            ->getContent();

        $anchor = $this->rowHtml($html, '27.10.2026');
        $this->assertStringContainsString('впереди ещё 4 дня', $anchor);
        $this->assertStringNotContainsString('еженедельно', $anchor);

        $later = $this->rowHtml($html, '03.11.2026');
        $this->assertStringContainsString('еженедельно, впереди ещё 4 дня', $later);

        $this->actingAs($teacher)
            ->get(route('teacher.slots.series.stop', $series->first()))
            ->assertOk()
            ->assertSee('Впереди ещё 4 дня.')
            ->assertSee('Будут сняты с публикации 3 пустых будущих дня.')
            ->assertSee('Занятые и уже начавшиеся останутся.');
    }

    public function test_empty_states_name_the_next_action(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $teacher = $this->teacherUser('Иванова А. С.');

        $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Избранных преподавателей нет')
            ->assertSee('Откройте преподавателей')
            ->assertSee(route('teachers.index'), false);

        $this->actingAs($teacher)
            ->get(route('teacher.slots.index'))
            ->assertOk()
            ->assertSee('Слотов пока нет')
            ->assertSee('Создайте первый слот')
            ->assertSee(route('teacher.slots.create'), false);

        $slot = $this->futureSlot($teacher, '11:00', '11:45', 'А', '12');

        $this->actingAs($teacher)
            ->get(route('slots.roster', $slot))
            ->assertOk()
            ->assertSee('На этот слот ещё никто не записался.')
            ->assertDontSee('Записей пока нет');
    }

    public function test_overlap_and_locked_time_are_one_sentence_next_to_the_field(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $student = $this->studentUser('Соколов Илья');
        $day = now()->addDays(3)->toDateString();
        $this->futureSlot($teacher, '10:00', '10:45', 'А', '101');

        $overlap = $this->followingRedirects()
            ->actingAs($teacher)
            ->from(route('teacher.slots.create'))
            ->post(route('teacher.slots.store'), [
                'format' => 'in_person',
                'date' => $day,
                'start_time' => '10:30',
                'end_time' => '11:15',
                'building' => 'Б',
                'room' => '202',
            ])
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($overlap, 'Это время пересекается с другим вашим слотом.'));
        $this->assertMatchesRegularExpression(
            '/id="start_time"[^>]*>\s*<p class="error" id="start_time-error">Это время пересекается с другим вашим слотом\.<\/p>/',
            $overlap,
        );
        $this->assertStringNotContainsString('class="notice error"', $overlap);

        $booked = $this->futureSlot($teacher, '12:00', '12:45', 'А', '303');
        $this->actingAs($student)->post(route('bookings.store', $booked))->assertRedirect();

        $lockedPage = $this->actingAs($teacher)
            ->get(route('teacher.slots.edit', $booked))
            ->assertOk()
            ->getContent();
        $this->assertSame(1, substr_count($lockedPage, 'Время нельзя изменить: на слот уже есть запись.'));
        $this->assertMatchesRegularExpression(
            '/id="start_time"[^>]*>\s*<p class="field-note" id="start_time-lock">Время нельзя изменить: на слот уже есть запись\.<\/p>/',
            $lockedPage,
        );

        $locked = $this->followingRedirects()
            ->actingAs($teacher)
            ->from(route('teacher.slots.edit', $booked))
            ->post(route('teacher.slots.update', $booked), [
                'format' => 'in_person',
                'date' => now()->addDays(5)->toDateString(),
                'start_time' => '15:00',
                'end_time' => '15:45',
                'building' => 'В',
                'room' => '8',
            ])
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($locked, 'Время нельзя изменить: на слот уже есть запись.'));
        $this->assertMatchesRegularExpression(
            '/id="start_time"[^>]*>\s*<p class="error" id="start_time-error">Время нельзя изменить: на слот уже есть запись\.<\/p>/',
            $locked,
        );
        $this->assertStringNotContainsString('id="start_time-lock"', $locked);
        $this->assertStringNotContainsString('class="notice error"', $locked);
        $this->assertSame('12:00', $booked->refresh()->starts_at->format('H:i'));

        $other = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => '2026-10-31 11:00:00',
            'ends_at' => '2026-10-31 11:45:00',
            'building' => 'А',
            'room' => '2',
            'is_published' => true,
        ]);
        $free = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => '2026-10-31 09:00:00',
            'ends_at' => '2026-10-31 09:45:00',
            'building' => 'А',
            'room' => '1',
            'is_published' => true,
        ]);

        $moved = $this->followingRedirects()
            ->actingAs($teacher)
            ->from(route('teacher.slots.edit', $free))
            ->post(route('teacher.slots.update', $free), [
                'format' => 'in_person',
                'date' => '2026-10-31',
                'start_time' => '11:00',
                'end_time' => '11:45',
                'building' => 'А',
                'room' => '9',
            ])
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($moved, 'Это время пересекается с другим вашим слотом.'));
        $this->assertMatchesRegularExpression(
            '/id="start_time"[^>]*>\s*<p class="error" id="start_time-error">Это время пересекается с другим вашим слотом\.<\/p>/',
            $moved,
        );
        $this->assertSame('09:00', $free->refresh()->starts_at->format('H:i'));
        $this->assertSame('11:00', $other->refresh()->starts_at->format('H:i'));

        $second = $this->futureSlot($teacher, '12:15', '13:00', 'Б', '4');
        $second->update(['building' => null, 'room' => null, 'meeting_url' => 'https://meet.example.com/later']);

        $studentOverlap = $this->followingRedirects()
            ->actingAs($student)
            ->from(route('bookings.create', $second))
            ->post(route('bookings.store', $second))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($studentOverlap, 'У вас уже есть запись на пересекающееся время.'));
        $this->assertMatchesRegularExpression(
            '/id="slot-error"[^>]*>У вас уже есть запись на пересекающееся время\.<\/p>\s*<p class="form-actions">/',
            $studentOverlap,
        );
        $this->assertStringNotContainsString('class="notice error"', $studentOverlap);
    }

    public function test_phone_cards_keep_one_primary_and_stack_secondary_actions(): void
    {
        $css = file_get_contents(public_path('css/app.css'));
        $this->assertIsString($css);
        $this->assertDoesNotMatchRegularExpression('/slot-actions[^{]*\{[^}]*display:\s*contents/s', $css);
        $this->assertMatchesRegularExpression('/\.slot-more\s*\{[^}]*flex-direction:\s*column/s', $css);
        $this->assertStringContainsString('overflow-x: clip', $css);

        $teacher = $this->teacherUser('Иванова А. С.');
        $student = $this->studentUser('Соколов Илья');

        $this->actingAs($teacher)->post(route('teacher.slots.store'), [
            'format' => 'in_person',
            'date' => '27.10.2026',
            'start_time' => '15:00',
            'end_time' => '15:45',
            'building' => 'А',
            'room' => '77',
            'repeat_weekly' => '1',
            'repeat_until' => '10.11.2026',
        ])->assertRedirect();

        $series = Slot::query()->where('room', '77')->orderBy('starts_at')->get();
        $this->actingAs($student)->post(route('bookings.store', $series->first()))->assertRedirect();

        $html = $this->actingAs($teacher)->get(route('teacher.slots.index'))->assertOk()->getContent();
        $later = $this->rowHtml($html, '03.11.2026');

        $this->assertSame(1, preg_match('/<div class="actions slot-primary">(.*?)<\/div>/s', $later, $primary));
        $this->assertSame(1, substr_count($primary[1], '<a '));
        $this->assertStringContainsString('Изменить', $primary[1]);
        $this->assertStringNotContainsString('Снять с публикации', $primary[1]);

        $this->assertSame(1, preg_match('/<div class="actions slot-more">(.*?)<\/div>/s', $later, $more));
        $this->assertStringContainsString('Снять с публикации', $more[1]);
        $this->assertStringNotContainsString('<table', $more[1]);
        $this->assertStringNotContainsString('Остановить повтор', $more[1]);

        $anchor = $this->rowHtml($html, '27.10.2026');
        $this->assertSame(1, preg_match('/<div class="actions slot-more">(.*?)<\/div>/s', $anchor, $anchorMore));
        $this->assertStringContainsString('Остановить повтор', $anchorMore[1]);
        $this->assertStringNotContainsString('<table', $anchorMore[1]);
        $this->assertSame(1, preg_match_all('/<div class="actions slot-primary">/', $anchor));
    }

    private function whereHtml(string $html, string $url): string
    {
        $pattern = '/<div class="where">(?:(?!<\/div>).)*'.preg_quote($url, '/').'(?:(?!<\/div>).)*<\/div>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }

    private function rowHtml(string $html, string $marker): string
    {
        $pattern = '/<tr\b[^>]*>(?:(?!<\/tr>).)*'.preg_quote($marker, '/').'(?:(?!<\/tr>).)*<\/tr>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }
}
