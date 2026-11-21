<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use Tests\ConsultationTestCase;

class AccessibilityMarkupTest extends ConsultationTestCase
{
    public function test_shell_skips_the_header_and_names_the_logo(): void
    {
        $html = $this->get(route('login'))
            ->assertOk()
            ->assertSee('alt="Пермский политех, запись на консультацию"', false)
            ->assertSee('<a class="skip-link" href="#content">К содержанию</a>', false)
            ->assertSee('<main id="content" tabindex="-1">', false)
            ->assertSee('<footer class="site-footer">', false)
            ->assertSee('Запись на консультацию', false)
            ->assertSee('Кафедра информационных технологий и автоматизированных систем', false)
            ->assertSee('href="https://pstu.ru/">ПНИПУ</a>', false)
            ->assertSee('href="https://pstu.ru/about-the-university/structure-and-governing-bodies/faculties/elektrotekhnicheskiy/">Электротехнический факультет</a>', false)
            ->assertSee('href="https://pstu.ru/about-the-university/structure-and-governing-bodies/faculties/departments/informatsionnye-tekhnologii-i-avtomatizirovannye-sistemy/o-kafedre/">Кафедра ИТАС</a>', false)
            ->assertSee('>Наверх</button>', false)
            ->getContent();

        $this->assertLessThan(strpos($html, '<header'), strpos($html, 'class="skip-link"'));
        $this->assertLessThan(strpos($html, '<footer'), strpos($html, '</main>'));

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertIsString($css);
        $this->assertStringContainsString('--line-strong: #5c6770;', $css);
        $this->assertMatchesRegularExpression(
            '/\.field input::placeholder,\s*\.field textarea::placeholder\s*\{[^}]*color:\s*#5c6770/s',
            $css,
        );
        $this->assertStringNotContainsString('#c3ccd4', $css);
        $this->assertStringNotContainsString('#8a94a0', $css);
        $this->assertStringNotContainsString('body:has(.auth)', $css);
        $this->assertMatchesRegularExpression(
            '/html\s*\{[^}]*height:\s*100%/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/body:not\(\.desk\)\s*\{[^}]*display:\s*flex;[^}]*flex-direction:\s*column;[^}]*min-height:\s*100%/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/body:not\(\.desk\) > main\s*\{[^}]*flex:\s*1 0 auto/s',
            $css,
        );
    }

    public function test_login_error_is_tied_to_the_email_field(): void
    {
        $student = $this->studentUser('Соколов Илья');

        $wrong = $this->from(route('login'))
            ->followingRedirects()
            ->post(route('login.store'), [
                'email' => $student->email,
                'password' => 'не-тот-пароль',
            ])
            ->assertOk()
            ->assertSee('Неверная почта или пароль.')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="email"[^>]*aria-invalid="true"[^>]*aria-describedby="email-error"/',
            $wrong,
        );
        $this->assertMatchesRegularExpression(
            '/id="email-error"[^>]*>Неверная почта или пароль\./',
            $wrong,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="password"[^>]*aria-invalid="true"/',
            $wrong,
        );
        $this->assertStringContainsString('autocomplete="username"', $wrong);

        $format = $this->from(route('login'))
            ->followingRedirects()
            ->post(route('login.store'), [
                'email' => 'не-почта',
                'password' => '',
            ])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/id="email-error"[^>]*>Почта указана неверно\./', $format);
        $this->assertMatchesRegularExpression('/id="password-error"[^>]*>Укажите пароль\./', $format);
        $this->assertMatchesRegularExpression(
            '/id="password"[^>]*aria-invalid="true"[^>]*aria-describedby="password-error"/',
            $format,
        );
    }

    public function test_register_and_password_errors_name_the_field(): void
    {
        $page = $this->get(route('register'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="email"[^>]*autocomplete="email"/', $page);
        $this->assertStringNotContainsString('autocomplete="username"', $page);

        $mismatch = $this->from(route('register'))
            ->followingRedirects()
            ->post(route('register.store'), [
                'name' => 'Иванов Иван',
                'group_name' => 'ПИ-21',
                'email' => 'ivanov@kafedra.local',
                'password' => 'Parol-123',
                'password_confirmation' => 'Parol-124',
            ])
            ->assertOk()
            ->assertSee('Пароль и повтор не совпадают.')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="password_confirmation-error"[^>]*>Пароль и повтор не совпадают\./',
            $mismatch,
        );
        $this->assertMatchesRegularExpression(
            '/id="password_confirmation"[^>]*aria-invalid="true"[^>]*aria-describedby="password_confirmation-error"/',
            $mismatch,
        );
        $this->assertDoesNotMatchRegularExpression('/id="password"[^>]*aria-invalid="true"/', $mismatch);

        $student = $this->studentUser('Соколов Илья');
        $current = $this->actingAs($student)
            ->from(route('password.edit'))
            ->followingRedirects()
            ->post(route('password.update'), [
                'current_password' => 'не-тот',
                'password' => 'Parol-123',
                'password_confirmation' => 'Parol-124',
            ])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="current_password"[^>]*aria-invalid="true"[^>]*aria-describedby="current_password-error"/',
            $current,
        );
        $this->assertMatchesRegularExpression(
            '/id="current_password-error"[^>]*>Текущий пароль неверный\./',
            $current,
        );
        $this->assertMatchesRegularExpression(
            '/id="password_confirmation"[^>]*aria-describedby="password_confirmation-error"/',
            $current,
        );
    }

    public function test_student_home_calendar_week_labels_and_booking_names(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $student = $this->studentUser('Соколов Илья');
        $student->studentProfile->favoriteTeachers()->attach($teacher->teacherProfile->id);
        $slot = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => '2026-10-28 10:00:00',
            'ends_at' => '2026-10-28 10:45:00',
            'building' => 'Главный А',
            'room' => '314',
            'is_published' => true,
        ]);

        $html = $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('>Преподаватели</a>', false)
            ->assertSee('>Мои записи</a>', false)
            ->assertSee('19–25 октября 2026', false)
            ->assertSee('Перейти к дате', false)
            ->assertSee('aria-label="Предыдущая неделя"', false)
            ->assertSee('aria-label="Следующая неделя"', false)
            ->assertDontSee('<span>Неделя с</span>', false)
            ->assertDontSee('<span>Неделя по</span>', false)
            ->assertDontSee('onchange=', false)
            ->assertDontSee('title="есть консультация"', false)
            ->getContent();

        $this->assertStringContainsString(
            'aria-label="28 октября 2026, есть консультация"',
            $html,
        );
        $week = $this->actingAs($student)
            ->get(route('home', ['from' => '2026-10-26', 'to' => '2026-11-01']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString(
            'aria-label="Записаться: Иванова А. С., 28.10.2026, 10:00–10:45"',
            $week,
        );
        $this->assertMatchesRegularExpression(
            '/data-date="2026-10-25"[^>]*>\s*<span>25<span class="visually-hidden">сегодня<\/span><\/span>/',
            $html,
        );
        $this->assertSame(1, preg_match('/<td\b[^>]*data-date="2026-09-28"[^>]*>(?<body>.*?)<\/td>/s', $html, $out));
        $this->assertSame('', trim($out['body']));

        $chosen = $this->actingAs($student)
            ->get(route('home', ['day' => '2026-10-28', 'teacher' => $teacher->teacherProfile->id]))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/aria-current="date"[^>]*aria-label="28 октября 2026, есть консультация"|aria-label="28 октября 2026, есть консультация"[^>]*aria-current="date"/',
            $chosen,
        );

        $bad = $this->actingAs($student)
            ->get(route('home', ['from' => '2026-11-10', 'to' => '2026-11-01']))
            ->assertOk()
            ->assertSee('Дата конца раньше даты начала.')
            ->getContent();
        $this->assertSame(1, substr_count($bad, 'aria-invalid="true"'));
        $this->assertStringContainsString('id="week-range-error"', $bad);
        $this->assertStringContainsString('aria-describedby="week-range-error"', $bad);

        $this->actingAs($student)
            ->get(route('home', ['from' => 'нет', 'to' => '']))
            ->assertOk()
            ->assertSee('Укажите дату.');

        $this->actingAs($student)
            ->get(route('teachers.index'))
            ->assertOk()
            ->assertSee('>Слоты избранных</a>', false)
            ->assertSee('id="teacher-search-status"', false)
            ->assertSee('aria-live="polite"', false);

        $this->assertNotSame(0, $slot->id);
    }

    public function test_teacher_actions_and_signup_link_name_the_slot(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $student = $this->studentUser('Соколов Илья');
        $slot = Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'starts_at' => '2026-11-02 16:00:00',
            'ends_at' => '2026-11-02 16:45:00',
            'building' => 'Главный А',
            'room' => '314',
            'is_published' => true,
        ]);
        Booking::query()->create([
            'slot_id' => $slot->id,
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::BOOKED,
        ]);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.slots.index', ['from' => '2026-10-19', 'to' => '2026-10-25']))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(strpos($html, 'id="next-slot-title"'), strpos($html, '<h1>Мои слоты</h1>'));
        $this->assertMatchesRegularExpression(
            '/<h2 class="next-slot-label" id="next-slot-title">Ближайшая консультация<\/h2>/',
            $html,
        );
        $this->assertStringContainsString('aria-label="Изменить: 02.11.2026, 16:00–16:45"', $html);
        $this->assertStringContainsString('aria-label="1 студент, 02.11.2026, 16:00–16:45"', $html);
        $this->assertStringContainsString('19–25 октября 2026', $html);
        $this->assertStringContainsString('Перейти к дате', $html);
    }

    public function test_slot_form_groups_room_warning_and_conduct_comment(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');

        $form = $this->followingRedirects()
            ->actingAs($teacher)
            ->from(route('teacher.slots.create'))
            ->post(route('teacher.slots.store'), [
                'format' => 'later',
                'repeat' => 'often',
                'date' => '2026-11-02',
                'start_time' => '16:00',
                'end_time' => '16:45',
                'building' => 'А',
                'room' => '101',
            ])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<fieldset\b[^>]*aria-describedby="format-error"[^>]*>[\s\S]*id="format_in_person"[^>]*aria-invalid="true"[\s\S]*id="format_online"[^>]*aria-invalid="true"[\s\S]*id="format-error">Выберите формат: очно или онлайн\./',
            $form,
        );
        $this->assertMatchesRegularExpression(
            '/<fieldset\b[^>]*aria-describedby="repeat-error"[^>]*>[\s\S]*id="repeat_none"[^>]*aria-invalid="true"[\s\S]*id="repeat-error">/',
            $form,
        );
        $this->assertMatchesRegularExpression(
            '/id="repeat-until-cleared"[^>]*role="alert"/',
            $form,
        );
        $this->assertStringContainsString('id="room-warning"', $form);
        $this->assertStringContainsString('role="status"', $form);
        $this->assertStringContainsString('aria-live="polite"', $form);
        $this->assertStringNotContainsString('replaceWith', $form);

        $script = file_get_contents(resource_path('views/teacher/create.blade.php'));
        $this->assertIsString($script);
        $this->assertStringContainsString('current.innerHTML = next.innerHTML', $script);
        $this->assertStringContainsString("until.setAttribute('aria-describedby', 'repeat-until-cleared')", $script);
        $this->assertStringNotContainsString('replaceWith', $script);

        $student = $this->studentUser('Соколов Илья');
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
        $conduct = $this->followingRedirects()
            ->actingAs($teacher)
            ->post(route('teacher.slots.conduct.store', $started), [
                'comment' => str_repeat('а', 501),
            ])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="comment"[^>]*aria-invalid="true"[^>]*aria-describedby="comment-error"/',
            $conduct,
        );
        $this->assertMatchesRegularExpression(
            '/id="comment-error"[^>]*>Комментарий не длиннее 500 символов\./',
            $conduct,
        );
        $this->assertSame(1, substr_count($conduct, 'Комментарий не длиннее 500 символов.'));
    }

    public function test_directory_search_status_and_profile_errors_are_described(): void
    {
        $admin = $this->adminUser();
        $teacher = $this->teacherUser('Иванова А. С.');

        $users = $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="user-search-status"', $users);
        $this->assertStringContainsString('aria-live="polite"', $users);

        $profile = $this->actingAs($admin)
            ->followingRedirects()
            ->post(route('admin.users.profile', $teacher), [
                'profile_user' => $teacher->id,
                'position' => str_repeat('д', 256),
                'department' => ' ',
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="position-error-'.$teacher->id.'"', $profile);
        $this->assertStringContainsString('id="department-error-'.$teacher->id.'"', $profile);
        $this->assertMatchesRegularExpression(
            '/aria-describedby="position-error-'.$teacher->id.'"/',
            $profile,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<label\b[^>]*>\s*Должность\s*<input\b[^>]*>\s*<p class="error"/',
            $profile,
        );

        $js = file_get_contents(public_path('js/tables.js'));
        $this->assertIsString($js);
        $this->assertStringContainsString("button.type = 'button'", $js);
        $this->assertStringContainsString('Показано ', $js);
        $this->assertStringContainsString('table-sort', $js);
        $this->assertStringContainsString("caption.textContent = 'Статус'", $js);
        $this->assertStringNotContainsString('header.tabIndex', $js);
        $this->assertStringNotContainsString("header.title = 'Сортировать'", $js);
        $this->assertStringNotContainsString("filterRow.className = 'filters'", $js);
        $this->assertStringNotContainsString("select.setAttribute('aria-label', 'Статус')", $js);
    }
}
