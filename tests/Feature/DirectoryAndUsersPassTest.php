<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SearchText;
use Tests\ConsultationTestCase;

class DirectoryAndUsersPassTest extends ConsultationTestCase
{
    public function test_search_keeps_a_name_and_department_typed_with_initials_or_a_comma(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $ivanova = $this->teacherUser('Иванова А. С.');
        $petrov = $this->teacherUser('Петров Н. И.');
        $petrov->teacherProfile->update([
            'position' => 'старший преподаватель',
            'department' => 'высшая математика',
        ]);

        $byInitials = $this->actingAs($student)
            ->get(route('teachers.index', ['q' => 'Иванова А.С.']))
            ->assertOk()
            ->assertSee('value="Иванова А.С."', false)
            ->getContent();

        $this->assertRowVisible($byInitials, 'Иванова А. С.');
        $this->assertRowHidden($byInitials, 'Петров Н. И.');
        $this->assertStringContainsString(
            'data-search="'.SearchText::fold('Иванова А. С. доцент программная инженерия').'"',
            $byInitials,
        );

        $together = $this->actingAs($student)
            ->get(route('teachers.index', ['q' => 'иванова, инженерия']))
            ->assertOk()
            ->getContent();
        $this->assertRowVisible($together, 'Иванова А. С.');
        $this->assertRowHidden($together, 'Петров Н. И.');

        $position = $this->actingAs($student)
            ->get(route('teachers.index', ['q' => 'доцент программная']))
            ->assertOk()
            ->getContent();
        $this->assertRowVisible($position, 'Иванова А. С.');
        $this->assertRowHidden($position, 'Петров Н. И.');

        $script = file_get_contents(public_path('js/tables.js'));
        $this->assertIsString($script);
        $this->assertStringContainsString("replace(/ё/g, 'е')", $script);
        $this->assertStringContainsString('word.indexOf(token) === 0', $script);
    }

    public function test_search_folds_yo_and_says_when_nothing_matches(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $this->teacherUser('Артём Фёдоров')->teacherProfile->update([
            'position' => 'ассистент',
            'department' => 'физика',
        ]);
        $this->teacherUser('Иванова А. С.');

        $folded = $this->actingAs($student)
            ->get(route('teachers.index', ['q' => 'федоров физика']))
            ->assertOk()
            ->getContent();
        $this->assertRowVisible($folded, 'Артём Фёдоров');
        $this->assertRowHidden($folded, 'Иванова А. С.');
        $this->assertMatchesRegularExpression('/<tr\b[^>]*data-nomatch[^>]*hidden/', $folded);

        $empty = $this->actingAs($student)
            ->get(route('teachers.index', ['q' => 'нет такого']))
            ->assertOk()
            ->getContent();
        $this->assertRowHidden($empty, 'Артём Фёдоров');
        $this->assertRowHidden($empty, 'Иванова А. С.');
        $this->assertMatchesRegularExpression('/<tr\b[^>]*data-nomatch(?![^>]*hidden)[^>]*>\s*<td[^>]*>Нет совпадений<\/td>/', $empty);
    }

    public function test_admin_search_finds_a_user_by_surname_and_by_initials(): void
    {
        $admin = $this->adminUser();
        $sokolov = $this->studentUser('Соколов Илья');
        $ivanova = $this->teacherUser('Иванова А. С.');
        $this->teacherUser('Фёдоров П. А.');

        $everyone = $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('value=""', false)
            ->getContent();
        $this->assertRowVisible($everyone, 'Соколов Илья');
        $this->assertRowVisible($everyone, 'Иванова А. С.');
        $this->assertRowVisible($everyone, 'Фёдоров П. А.');
        $this->assertStringContainsString('>Заблокировать</a>', $this->row($everyone, 'Соколов Илья'));
        $this->assertStringContainsString(route('admin.users.password.confirm', $ivanova), $this->row($everyone, 'Иванова А. С.'));
        $this->assertMatchesRegularExpression('/<tr\b[^>]*data-nomatch[^>]*hidden/', $everyone);

        $bySurname = $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => 'Соколов']))
            ->assertOk()
            ->assertSee('value="Соколов"', false)
            ->getContent();
        $this->assertRowVisible($bySurname, 'Соколов Илья');
        $this->assertRowHidden($bySurname, 'Иванова А. С.');
        $this->assertRowHidden($bySurname, 'Фёдоров П. А.');
        $this->assertStringContainsString(route('admin.users.block.confirm', $sokolov), $this->row($bySurname, 'Соколов Илья'));
        $this->assertStringContainsString(route('admin.users.password.confirm', $sokolov), $this->row($bySurname, 'Соколов Илья'));
        $this->assertStringContainsString('>Заблокировать</a>', $this->row($bySurname, 'Соколов Илья'));
        $this->assertStringContainsString('>Новый пароль</a>', $this->row($bySurname, 'Соколов Илья'));

        $byInitials = $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => 'Иванова А.С.']))
            ->assertOk()
            ->assertSee('value="Иванова А.С."', false)
            ->getContent();
        $this->assertRowVisible($byInitials, 'Иванова А. С.');
        $this->assertRowHidden($byInitials, 'Соколов Илья');
        $this->assertStringContainsString(route('admin.users.block.confirm', $ivanova), $this->row($byInitials, 'Иванова А. С.'));
        $this->assertStringContainsString(route('admin.users.password.confirm', $ivanova), $this->row($byInitials, 'Иванова А. С.'));
        $this->assertStringContainsString(
            'data-search="'.SearchText::fold('Иванова А. С.').'"',
            $byInitials,
        );

        $withComma = $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => 'соколов, илья']))
            ->assertOk()
            ->getContent();
        $this->assertRowVisible($withComma, 'Соколов Илья');
        $this->assertRowHidden($withComma, 'Иванова А. С.');

        $folded = $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => 'федоров']))
            ->assertOk()
            ->getContent();
        $this->assertRowVisible($folded, 'Фёдоров П. А.');
        $this->assertRowHidden($folded, 'Соколов Илья');

        $empty = $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => 'нет такого']))
            ->assertOk()
            ->getContent();
        $this->assertRowHidden($empty, 'Соколов Илья');
        $this->assertRowHidden($empty, 'Иванова А. С.');
        $this->assertMatchesRegularExpression('/<tr\b[^>]*data-nomatch(?![^>]*hidden)[^>]*>\s*<td[^>]*>Нет совпадений<\/td>/', $empty);

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.directory-search input\s*\{[^}]*max-width:\s*100%;[^}]*min-width:\s*0/s',
            $css,
        );
        $script = file_get_contents(public_path('js/tables.js'));
        $this->assertIsString($script);
        $this->assertStringContainsString('data-user-search', $script);
        $this->assertStringContainsString('data-user-directory', $script);
    }

    public function test_favorite_row_says_whether_the_teacher_is_saved(): void
    {
        $student = $this->studentUser('Соколов Илья');
        $ivanova = $this->teacherUser('Иванова А. С.');
        $petrov = $this->teacherUser('Петров Н. И.');

        $before = $this->actingAs($student)
            ->get(route('teachers.index'))
            ->assertOk()
            ->assertSee('Избранных нет')
            ->getContent();
        $this->assertStringContainsString('aria-label="Добавить в избранное"', $this->row($before, 'Иванова А. С.'));
        $this->assertStringNotContainsString('data-favorite', $this->openingTag($before, 'Иванова А. С.'));

        $this->actingAs($student)
            ->post(route('teachers.favorite', $petrov->teacherProfile))
            ->assertRedirect(route('teachers.index'))
            ->assertSessionHas('status', 'Преподаватель добавлен в избранное.');

        $saved = $this->actingAs($student)
            ->get(route('teachers.index'))
            ->assertOk()
            ->assertSee('В избранном 1')
            ->getContent();
        $petrovRow = $this->row($saved, 'Петров Н. И.');
        $this->assertStringContainsString('data-favorite', $this->openingTag($saved, 'Петров Н. И.'));
        $this->assertStringContainsString('>В избранном</span>', $petrovRow);
        $this->assertStringContainsString('aria-label="Убрать из избранного"', $petrovRow);
        $this->assertStringContainsString('aria-label="Добавить в избранное"', $this->row($saved, 'Иванова А. С.'));
        $this->assertLessThan(strpos($saved, 'Иванова А. С.'), strpos($saved, 'Петров Н. И.'));

        $this->actingAs($student)
            ->post(route('teachers.unfavorite', $petrov->teacherProfile))
            ->assertRedirect(route('teachers.index'))
            ->assertSessionHas('status', 'Преподаватель убран из избранного.');

        $after = $this->actingAs($student)->get(route('teachers.index'))->assertOk()->getContent();
        $this->assertStringContainsString('aria-label="Добавить в избранное"', $this->row($after, 'Петров Н. И.'));
        $this->assertStringNotContainsString('>В избранном</span>', $this->row($after, 'Петров Н. И.'));
        $this->assertStringNotContainsString('data-favorite', $this->openingTag($after, 'Петров Н. И.'));
    }

    public function test_unblock_and_password_reset_ask_before_changing_the_account(): void
    {
        $admin = $this->adminUser();
        $student = $this->studentUser('Соколов Илья');
        $student->update(['is_active' => false]);
        $teacher = $this->teacherUser('Иванова А. С.');
        $before = $teacher->password;

        $index = $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString(route('admin.users.unblock.confirm', $student), $index);
        $this->assertStringNotContainsString('action="'.route('admin.users.unblock', $student).'"', $index);
        $this->assertStringContainsString(route('admin.users.password.confirm', $teacher), $index);
        $this->assertStringNotContainsString('action="'.route('admin.users.password', $teacher).'"', $index);
        $this->assertStringContainsString('class="person-mail"', $this->row($index, 'Соколов Илья'));
        $this->assertStringContainsString($student->email, $this->row($index, 'Соколов Илья'));

        $this->actingAs($admin)
            ->get(route('admin.users.unblock.confirm', $student))
            ->assertOk()
            ->assertSee('Соколов Илья')
            ->assertSee($student->email)
            ->assertSee('Вход снова станет доступен')
            ->assertSee('К пользователям');
        $this->assertFalse($student->refresh()->is_active);

        $other = $this->studentUser('Белова Анна');
        $this->actingAs($other)
            ->get(route('admin.users.unblock.confirm', $student))
            ->assertForbidden();
        $this->actingAs($other)
            ->get(route('admin.users.password.confirm', $teacher))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.users.password.confirm', $teacher))
            ->assertOk()
            ->assertSee('Иванова А. С.')
            ->assertSee($teacher->email)
            ->assertSee('Передайте временный пароль лично: письма нет.')
            ->assertSee('Не короче 8 символов.');
        $this->assertSame($before, $teacher->refresh()->password);

        $this->actingAs($admin)
            ->from(route('admin.users.password.confirm', $teacher))
            ->post(route('admin.users.password', $teacher), ['password' => 'short'])
            ->assertRedirect(route('admin.users.password.confirm', $teacher))
            ->assertSessionHasErrors([
                'password' => 'Пароль не короче 8 символов.',
            ]);
        $this->assertSame($before, $teacher->refresh()->password);

        $form = $this->followingRedirects()
            ->actingAs($admin)
            ->from(route('admin.users.password.confirm', $teacher))
            ->post(route('admin.users.password', $teacher), ['password' => 'short'])
            ->assertOk()
            ->assertSee('Пароль не короче 8 символов.')
            ->getContent();
        $this->assertStringContainsString('id="password-error"', $form);
        $this->assertStringNotContainsString('The ', $form);

        $this->actingAs($admin)
            ->post(route('admin.users.unblock', $student))
            ->assertRedirect(route('admin.users.index'));
        $this->assertTrue($student->refresh()->is_active);
    }

    public function test_block_confirm_names_the_person_and_does_not_block_on_open(): void
    {
        $admin = $this->adminUser();
        $student = $this->studentUser('Соколов Илья');

        $this->actingAs($admin)
            ->get(route('admin.users.block.confirm', $student))
            ->assertOk()
            ->assertSee('Соколов Илья')
            ->assertSee($student->email)
            ->assertSee('студент')
            ->assertSee('Вход станет недоступен, открытая сессия прервётся.');
        $this->assertTrue($student->refresh()->is_active);
    }

    public function test_new_teacher_form_is_absent_until_the_button_and_stays_open_on_errors(): void
    {
        $admin = $this->adminUser();

        $closed = $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('>Новый</button>', false)
            ->assertSee('aria-label="Новый пользователь"', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<button\b[^>]*data-new-user[^>]*aria-expanded="false"[^>]*>Новый<\/button>/',
            $closed,
        );
        $this->assertMatchesRegularExpression(
            '/<section\b[^>]*id="new-user"[^>]*\bhidden\b[^>]*>[\s\S]*Преподаватели не регистрируются сами\.[\s\S]*Кого создать[\s\S]*>Создать<\/button>[\s\S]*Отмена/',
            $closed,
        );
        $this->assertLessThan(
            strpos($closed, 'id="new-user"'),
            strpos($closed, 'data-new-user'),
        );

        $script = file_get_contents(public_path('js/tables.js'));
        $this->assertIsString($script);
        $this->assertStringContainsString('data-new-user', $script);
        $this->assertStringContainsString('data-new-user-cancel', $script);
        $this->assertStringContainsString('setOpen(panel.hidden)', $script);

        $open = $this->followingRedirects()
            ->actingAs($admin)
            ->post(route('admin.teachers.store'), [
                'form' => 'teacher',
                'name' => '',
                'email' => 'not-an-email',
                'position' => '',
                'department' => '',
                'password' => 'short',
            ])
            ->assertOk()
            ->assertSee('Укажите ФИО.')
            ->assertSee('Почта указана неверно.')
            ->assertSee('Укажите должность.')
            ->assertSee('Укажите кафедру.')
            ->assertSee('Пароль не короче 8 символов.')
            ->assertSee('Преподаватели не регистрируются сами.')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<section\b[^>]*id="new-user"(?![^>]*\bhidden\b)[^>]*>/',
            $open,
        );
        $this->assertMatchesRegularExpression(
            '/<button\b[^>]*data-new-user[^>]*aria-expanded="true"/',
            $open,
        );
        $this->assertStringContainsString('id="name-error"', $open);
        $this->assertStringContainsString('id="email-error"', $open);
        $this->assertStringContainsString('id="position-error"', $open);
        $this->assertStringContainsString('id="department-error"', $open);
        $this->assertStringContainsString('id="password-error"', $open);
        $this->assertStringNotContainsString('The ', $open);
    }

    public function test_new_teacher_errors_name_the_field_and_profile_errors_stay_on_that_teacher(): void
    {
        $admin = $this->adminUser();
        $teacher = $this->teacherUser('Иванова А. С.');
        $longDepartment = str_repeat('к', 256);

        $this->actingAs($admin)
            ->post(route('admin.teachers.store'), [
                'form' => 'teacher',
                'name' => ' ',
                'email' => 'not-an-email',
                'position' => '',
                'department' => $longDepartment,
                'password' => 'short',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors([
                'name' => 'Укажите ФИО.',
                'email' => 'Почта указана неверно.',
                'position' => 'Укажите должность.',
                'department' => 'Кафедра не длиннее 255 символов.',
                'password' => 'Пароль не короче 8 символов.',
            ]);

        $failed = $this->followingRedirects()
            ->actingAs($admin)
            ->post(route('admin.teachers.store'), [
                'form' => 'teacher',
                'name' => ' ',
                'email' => 'not-an-email',
                'position' => '',
                'department' => $longDepartment,
                'password' => 'short',
            ])
            ->assertOk()
            ->assertSee('Укажите ФИО.')
            ->assertSee('Почта указана неверно.')
            ->assertSee('Укажите должность.')
            ->assertSee('Кафедра не длиннее 255 символов.')
            ->assertSee('Пароль не короче 8 символов.')
            ->assertSee('Преподаватели не регистрируются сами.')
            ->getContent();

        $this->assertStringNotContainsString('The ', $failed);
        $this->assertStringContainsString('id="name-error"', $failed);
        $this->assertStringContainsString('id="email-error"', $failed);
        $this->assertStringContainsString('id="department-error"', $failed);
        $this->assertStringContainsString('id="password-error"', $failed);
        $this->assertDatabaseMissing('users', ['name' => ' ']);
        $this->assertNull(User::query()->where('email', 'not-an-email')->first());

        $this->actingAs($admin)->post(route('admin.teachers.store'), [
            'form' => 'teacher',
            'name' => 'Новикова И. П.',
            'email' => $teacher->email,
            'position' => 'доцент',
            'department' => 'программная инженерия',
            'password' => 'Vremen-123',
        ])->assertSessionHasErrors([
            'email' => 'Эта почта уже занята.',
        ]);
        $this->assertSame(1, User::query()->where('email', $teacher->email)->count());

        $longPosition = str_repeat('д', 256);
        $this->actingAs($admin)
            ->post(route('admin.users.profile', $teacher), [
                'profile_user' => $teacher->id,
                'position' => $longPosition,
                'department' => ' ',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors([
                'position' => 'Должность не длиннее 255 символов.',
                'department' => 'Укажите кафедру.',
            ]);

        $profile = $this->followingRedirects()
            ->actingAs($admin)
            ->post(route('admin.users.profile', $teacher), [
                'profile_user' => $teacher->id,
                'position' => $longPosition,
                'department' => ' ',
            ])
            ->assertOk()
            ->assertSee('Должность не длиннее 255 символов.')
            ->assertSee('Укажите кафедру.')
            ->getContent();

        $this->assertStringContainsString(' open', $this->row($profile, 'Иванова А. С.'));
        $this->assertStringContainsString('value="'.$longPosition.'"', $this->row($profile, 'Иванова А. С.'));
        $this->assertDoesNotMatchRegularExpression('/id="position"[^0-9-][^>]*value="'.$longPosition.'"/', $profile);
        $this->assertMatchesRegularExpression('/id="position"[^0-9-][^>]*value=""/', $profile);
        $this->assertSame('доцент', $teacher->teacherProfile->refresh()->position);
    }

    public function test_admin_student_form_asks_for_a_group_and_not_a_position(): void
    {
        $admin = $this->adminUser();

        $open = $this->followingRedirects()
            ->actingAs($admin)
            ->post(route('admin.teachers.store'), [
                'form' => 'user',
                'kind' => 'student',
                'name' => 'Новиков Пётр',
                'email' => 'novikov-p@kafedra.local',
                'group_name' => ' ',
                'password' => 'Vremen-123',
            ])
            ->assertOk()
            ->assertSee('Укажите учебную группу.')
            ->assertDontSee('Укажите должность.')
            ->assertDontSee('Укажите кафедру.')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<section\b[^>]*id="new-user"(?![^>]*\bhidden\b)[^>]*>/',
            $open,
        );
        $this->assertMatchesRegularExpression('/name="kind" value="student"[^>]*\bchecked\b/', $open);
        $this->assertStringContainsString('id="group_name-error"', $open);
        $this->assertStringContainsString('aria-describedby="group_name-error"', $open);
        $this->assertNull(User::query()->where('email', 'novikov-p@kafedra.local')->first());
    }

    public function test_directory_and_admin_lists_can_wrap_instead_of_spilling(): void
    {
        $css = file_get_contents(public_path('css/app.css'));
        $this->assertIsString($css);
        $this->assertStringContainsString('.table-wrap.teacher-directory td', $css);
        $this->assertStringContainsString('.table-wrap.admin-users td', $css);
        $this->assertStringContainsString('white-space: normal', $css);
        $this->assertStringContainsString('.person-cards .person-actions details:not([open]) > form', $css);
        $this->assertStringContainsString('.password-box[open] > form', $css);
        $this->assertDoesNotMatchRegularExpression('/\.password-box form\s*\{[^}]*display:\s*flex/s', $css);
        $this->assertStringNotContainsString('.week {', substr($css, strpos($css, '/* Teacher directory and admin users')));
    }

    private function row(string $html, string $marker): string
    {
        $pattern = '/<tr\b[^>]*>(?:(?!<\/tr>).)*'.preg_quote($marker, '/').'(?:(?!<\/tr>).)*<\/tr>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }

    private function openingTag(string $html, string $marker): string
    {
        $pattern = '/<tr\b([^>]*)>(?:(?!<\/tr>).)*'.preg_quote($marker, '/').'/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[1];
    }

    private function assertRowVisible(string $html, string $marker): void
    {
        $this->assertStringNotContainsString('hidden', $this->openingTag($html, $marker));
    }

    private function assertRowHidden(string $html, string $marker): void
    {
        $this->assertStringContainsString('hidden', $this->openingTag($html, $marker));
    }
}
