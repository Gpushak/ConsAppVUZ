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
            ->get(route('teacher.slots.index', $this->listedSpan()))
            ->assertOk()
            ->getContent();

        $anchor = $this->rowHtml($html, '27.10.2026');
        $this->assertStringContainsString('впереди ещё 3 дня', $anchor);
        $this->assertStringNotContainsString('еженедельно', $anchor);

        $later = $this->rowHtml($html, '03.11.2026');
        $this->assertStringContainsString('еженедельно', $later);
        $this->assertStringNotContainsString('впереди ещё', $later);

        $last = $this->rowHtml($html, '17.11.2026');
        $this->assertStringContainsString('еженедельно', $last);
        $this->assertStringNotContainsString('впереди ещё', $last);
        $this->assertSame(1, substr_count($html, 'впереди ещё'));

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

        $html = $this->actingAs($teacher)->get(route('teacher.slots.index', $this->listedSpan()))->assertOk()->getContent();
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

    public function test_favorites_without_open_slots_name_the_next_step(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $teacher = $this->teacherUser('Иванова А. С.');
        $student->studentProfile->favoriteTeachers()->attach($teacher->teacherProfile->id);

        $empty = $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Свободных слотов нет')
            ->assertSee('Откройте преподавателей')
            ->assertSee(route('teachers.index'), false)
            ->assertDontSee('Показать завершённые')
            ->assertDontSee('data-only-finished', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/Свободных слотов нет\.\s*<a href="[^"]*">Откройте преподавателей<\/a>, чтобы выбрать, чьи слоты смотреть\./',
            $empty,
        );

        Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => now()->subDay()->setTime(10, 0),
            'ends_at' => now()->subDay()->setTime(10, 45),
            'building' => 'Главный А',
            'room' => '314',
            'is_published' => true,
            'conducted_at' => now()->subDay()->setTime(10, 45),
        ]);

        $finished = $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Свободных слотов нет')
            ->assertSee('Показать завершённые')
            ->assertSee('откройте преподавателей')
            ->assertSee(route('teachers.index'), false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/Свободных слотов нет\.\s*Нажмите «Показать завершённые» или <a href="[^"]*">откройте преподавателей<\/a>\./',
            $finished,
        );
        $this->assertMatchesRegularExpression('/<div\s+data-only-finished\s+hidden\s*>/', $finished);
        $this->assertStringContainsString('Иванова А. С.', $finished);
    }

    public function test_phone_lists_are_cards_without_a_wide_table(): void
    {
        $css = file_get_contents(public_path('css/app.css'));
        $this->assertIsString($css);
        $phone = $this->phoneCss($css);

        $this->assertMatchesRegularExpression(
            '/\.place-link\s*\{[^}]*max-width:\s*100%;[^}]*overflow-wrap:\s*anywhere;[^}]*word-break:\s*break-all/s',
            $css,
        );
        $this->assertLessThan(
            strpos($phone, '.person-cards table'),
            strpos($phone, 'width: max-content'),
        );
        $this->assertMatchesRegularExpression(
            '/\.table-wrap\.person-cards,\s*\.table-wrap\.roster-cards\s*\{[^}]*overflow-x:\s*clip/s',
            $phone,
        );
        $this->assertMatchesRegularExpression(
            '/\.person-cards table,\s*\.roster-cards table,[\s\S]*?display:\s*block;[\s\S]*?width:\s*100%;[\s\S]*?min-width:\s*0;/s',
            $phone,
        );
        $this->assertMatchesRegularExpression(
            '/\.person-cards thead,\s*\.roster-cards thead\s*\{[^}]*display:\s*none/s',
            $phone,
        );
        $this->assertMatchesRegularExpression(
            '/\.person-cards tr\.person-card,\s*\.roster-cards tr\.roster-card\s*\{[^}]*flex-direction:\s*column/s',
            $phone,
        );
        $this->assertMatchesRegularExpression(
            '/\.person-cards tr\.person-card > td\.person-extra\s*\{[^}]*display:\s*none/s',
            $phone,
        );
        $this->assertMatchesRegularExpression(
            '/\.person-cards \.person-actions,\s*\.roster-cards \.roster-actions,[\s\S]*?flex-direction:\s*column/s',
            $phone,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.person-cards table[^{]*\{[^}]*width:\s*max-content/s',
            $phone,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.roster-cards table[^{]*\{[^}]*width:\s*max-content/s',
            $phone,
        );

        $student = $this->studentUser('Соколов Илья', 'ПИ-21');
        $other = $this->studentUser('Белова Анна', 'ПИ-22');
        $teacher = $this->teacherUser('Иванова А. С.');
        $admin = $this->adminUser();

        $directory = $this->actingAs($student)
            ->get(route('teachers.index'))
            ->assertOk()
            ->getContent();
        $teacherCard = $this->cardHtml($directory, 'person-card', 'Иванова А. С.');
        $this->assertStringContainsString('class="person-name"', $teacherCard);
        $this->assertStringContainsString('class="person-fact"', $teacherCard);
        $this->assertStringContainsString('доцент', $teacherCard);
        $this->assertStringContainsString('class="person-actions"', $teacherCard);
        $this->assertStringContainsString('>Добавить в избранное</button>', $teacherCard);
        $this->assertStringNotContainsString('<table', $teacherCard);
        $this->assertSame(1, substr_count($directory, '<table'));

        $users = $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();
        $adminCard = $this->cardHtml($users, 'person-card', 'Иванова А. С.');
        $this->assertStringContainsString('class="person-name"', $adminCard);
        $this->assertStringContainsString('class="person-fact"', $adminCard);
        $this->assertStringContainsString('доцент, программная инженерия', $adminCard);
        $this->assertStringContainsString('class="person-state"', $adminCard);
        $this->assertStringContainsString('активен', $adminCard);
        $this->assertStringContainsString('class="person-actions"', $adminCard);
        $this->assertStringContainsString('>Заблокировать</a>', $adminCard);
        $this->assertStringNotContainsString('<table', $adminCard);
        $studentCard = $this->cardHtml($users, 'person-card', 'Соколов Илья');
        $this->assertStringContainsString('class="person-fact"', $studentCard);
        $this->assertStringContainsString('ПИ-21', $studentCard);
        $this->assertStringContainsString('активен', $studentCard);
        $this->assertSame(1, substr_count($users, '<table'));

        $slot = $this->futureSlot($teacher, '11:00', '11:45', 'Главный А', '314');
        $this->actingAs($student)->post(route('bookings.store', $slot))->assertRedirect();
        $this->actingAs($other)->post(route('bookings.store', $slot))->assertRedirect();

        $roster = $this->actingAs($teacher)
            ->get(route('slots.roster', $slot))
            ->assertOk()
            ->assertSeeInOrder(['ПИ-21', 'Соколов Илья', 'ПИ-22', 'Белова Анна'])
            ->getContent();
        $this->assertSame(1, substr_count($roster, '<table'));
        $rosterCard = $this->cardHtml($roster, 'roster-card', 'Соколов Илья');
        $this->assertStringContainsString('class="roster-group"', $rosterCard);
        $this->assertStringContainsString('>Группа<', $rosterCard);
        $this->assertStringContainsString('ПИ-21', $rosterCard);
        $this->assertStringContainsString('class="roster-name"', $rosterCard);
        $this->assertStringContainsString('Соколов Илья', $rosterCard);
        $this->assertStringContainsString('class="roster-status"', $rosterCard);
        $this->assertStringContainsString('>Отменить</a>', $rosterCard);
        $this->assertStringNotContainsString('<table', $rosterCard);
        $this->assertStringContainsString('class="roster-card"', $this->cardHtml($roster, 'roster-card', 'Белова Анна'));
    }

    public function test_native_date_control_does_not_claim_dd_mm_yyyy(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $create = $this->actingAs($teacher)
            ->get(route('teacher.slots.create'))
            ->assertOk()
            ->assertSee('type="date"', false)
            ->getContent();

        $this->assertStringNotContainsString('ДД.ММ.ГГГГ', $create);
        $this->assertStringNotContainsString('placeholder="31.10.2026"', $create);

        $slot = $this->futureSlot($teacher, '10:00', '10:45', 'А', '12');
        $edit = $this->actingAs($teacher)
            ->get(route('teacher.slots.edit', $slot))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('ДД.ММ.ГГГГ', $edit);

        $failed = $this->followingRedirects()
            ->actingAs($teacher)
            ->from(route('teacher.slots.create'))
            ->post(route('teacher.slots.store'), [
                'format' => 'online',
                'date' => '31.02.2026',
                'start_time' => '18:00',
                'end_time' => '18:45',
                'meeting_url' => 'https://meet.example.com/x',
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('ДД.ММ.ГГГГ', $failed);
        $this->assertStringContainsString('Укажите дату.', $failed);
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

    private function cardHtml(string $html, string $class, string $marker): string
    {
        $pattern = '/<tr class="'.preg_quote($class, '/').'"[^>]*>(?:(?!<\/tr>).)*'.preg_quote($marker, '/').'(?:(?!<\/tr>).)*<\/tr>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
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
        $pattern = '/<(?:article|tr)\b[^>]*>(?:(?!<\/(?:article|tr)>).)*'.preg_quote($marker, '/').'(?:(?!<\/(?:article|tr)>).)*<\/(?:article|tr)>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }
}
