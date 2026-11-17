<?php

namespace Tests\Feature;

use App\Support\DesktopWindow;
use Tests\ConsultationTestCase;

class DesktopWindowTest extends ConsultationTestCase
{
    public function test_browser_layout_is_unchanged_without_the_electron_flag(): void
    {
        $student = $this->studentUser();
        $teacher = $this->teacherUser();
        $slot = $this->futureSlot($teacher, '11:00', '11:45');

        $home = $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<header class="top">', $home);
        $this->assertStringContainsString('css/app.css', $home);
        $this->assertStringContainsString('Слоты избранных', $home);
        $this->assertStringContainsString('Выйти', $home);
        $this->assertStringNotContainsString('desk-frame', $home);
        $this->assertStringNotContainsString('css/desktop.css', $home);
        $this->assertStringNotContainsString('data-desktop-dialog', $home);
        $this->assertStringNotContainsString('js/desktop.js', $home);

        $confirm = $this->actingAs($student)
            ->get(route('bookings.create', $slot))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<header class="top">', $confirm);
        $this->assertStringContainsString('Подтверждение записи', $confirm);
        $this->assertStringNotContainsString('desk-frame', $confirm);
        $this->assertStringNotContainsString('data-desktop-dialog', $confirm);
        $this->assertStringNotContainsString('css/desktop.css', $confirm);
    }

    public function test_desktop_frame_is_used_only_when_the_electron_flag_is_set(): void
    {
        $student = $this->studentUser();
        $teacher = $this->teacherUser();
        $slot = $this->futureSlot($teacher, '11:00', '11:45');

        $home = $this->actingAs($student)
            ->withHeader(DesktopWindow::HEADER, '1')
            ->get(route('home'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="desk-frame"', $home);
        $this->assertStringContainsString('class="desk-side"', $home);
        $this->assertStringContainsString('css/desktop.css', $home);
        $this->assertStringContainsString('css/app.css', $home);
        $this->assertStringContainsString('js/desktop.js', $home);
        $this->assertStringContainsString('Слоты избранных', $home);
        $this->assertStringContainsString('Преподаватели', $home);
        $this->assertStringContainsString('Мои записи', $home);
        $this->assertStringNotContainsString('<header class="top">', $home);
        $this->assertStringNotContainsString('data-desktop-dialog', $home);

        $confirm = $this->actingAs($student)
            ->withHeader(DesktopWindow::HEADER, '1')
            ->get(route('bookings.create', $slot))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="desk-frame"', $confirm);
        $this->assertStringContainsString('data-desktop-dialog', $confirm);
        $this->assertStringContainsString('Подтверждение записи', $confirm);
        $this->assertStringNotContainsString('<header class="top">', $confirm);

        $create = $this->actingAs($teacher)
            ->withHeader(DesktopWindow::HEADER, '1')
            ->get(route('teacher.slots.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-desktop-dialog', $create);
        $this->assertStringContainsString('Новый слот', $create);
        $this->assertStringNotContainsString('<header class="top">', $create);
    }

    public function test_electron_user_agent_uses_the_same_desktop_frame(): void
    {
        $student = $this->studentUser();

        $home = $this->actingAs($student)
            ->withHeader('User-Agent', 'Mozilla/5.0 '.DesktopWindow::USER_AGENT)
            ->get(route('home'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="desk-frame"', $home);
        $this->assertStringNotContainsString('<header class="top">', $home);
        $this->assertStringNotContainsString('data-desktop-dialog', $home);
    }

    public function test_dialog_fragment_is_marked_without_the_sidebar(): void
    {
        $student = $this->studentUser();
        $teacher = $this->teacherUser();
        $slot = $this->futureSlot($teacher, '11:00', '11:45');

        $fragment = $this->actingAs($student)
            ->withHeader(DesktopWindow::HEADER, '1')
            ->withHeader(DesktopWindow::FRAGMENT, '1')
            ->get(route('bookings.create', $slot))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-desktop-fragment', $fragment);
        $this->assertStringContainsString('data-desktop-dialog', $fragment);
        $this->assertStringContainsString('Подтверждение записи', $fragment);
        $this->assertStringNotContainsString('desk-side', $fragment);
        $this->assertStringNotContainsString('desk-frame', $fragment);
    }
}
