<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\ConsultationTestCase;

class SlotFoldTest extends ConsultationTestCase
{
    public function test_slot_card_details_are_hidden_until_that_card_is_opened(): void
    {
        $teacher = $this->teacherUser('Иванова А. С.');
        $student = $this->studentUser('Соколов Илья');
        $student->studentProfile->favoriteTeachers()->attach($teacher->teacherProfile->id);

        $seriesId = (string) Str::uuid();
        $booked = $this->makeSlot($teacher, '2026-10-27 10:00:00', '2026-10-27 10:45:00', '314', 'черновик курсовой', $seriesId);
        $this->makeSlot($teacher, '2026-11-03 10:00:00', '2026-11-03 10:45:00', '318', 'следующая неделя', $seriesId);
        $open = $this->makeSlot($teacher, '2026-10-27 16:00:00', '2026-10-27 16:45:00', '220', 'разбор лабораторной');

        Booking::query()->create([
            'slot_id' => $booked->id,
            'student_profile_id' => $student->studentProfile->id,
            'status' => Booking::BOOKED,
        ]);

        $home = $this->actingAs($student)
            ->get(route('home', $this->listedSpan()))
            ->assertOk()
            ->assertSee('<span>Неделя с</span>', false)
            ->assertSee('<span>Неделя по</span>', false)
            ->assertSee('>Показать</button>', false)
            ->assertSee('Предыдущая неделя')
            ->assertSee('Следующая неделя')
            ->getContent();

        $this->assertFoldedDay($home, '27.10.2026', [
            'ауд. 314' => ['Вы записаны', 'черновик курсовой', 'доцент'],
            'ауд. 220' => ['Записаться', 'разбор лабораторной', 'доцент'],
        ], true);

        $slots = $this->actingAs($teacher)
            ->get(route('teacher.slots.index', $this->listedSpan()))
            ->assertOk()
            ->assertSee('<span>Неделя с</span>', false)
            ->assertSee('<span>Неделя по</span>', false)
            ->assertSee('>Показать</button>', false)
            ->assertSee('Предыдущая неделя')
            ->assertSee('Следующая неделя')
            ->getContent();

        $this->assertFoldedDay($slots, '27.10.2026', [
            'ауд. 314' => ['Изменить', 'Остановить повтор', 'каждую неделю', 'впереди ещё 1 день', 'черновик курсовой'],
            'ауд. 220' => ['Изменить', 'Снять с публикации', 'разбор лабораторной'],
        ], false);

        $later = $this->cardHtml($this->dayHtml($slots, '03.11.2026'), 'ауд. 318');
        [$laterButton, $laterDetails] = $this->splitCard($later);
        $this->assertStringContainsString('каждую неделю', $laterDetails);
        $this->assertStringNotContainsString('каждую неделю', $laterButton);
        $this->assertStringNotContainsString('еженедельно', $laterDetails);
        $this->assertStringNotContainsString('впереди ещё', $laterButton);

        foreach ([$home, $slots] as $html) {
            $empty = $this->dayHtml($html, '26.10.2026');
            $this->assertStringContainsString('нет слотов', $empty);
            $this->assertStringNotContainsString('data-slot-card', $empty);
            $this->assertStringNotContainsString('slot-toggle', $empty);
            $this->assertSame(0, substr_count($html, 'aria-expanded="true"'));
            $this->assertGreaterThan(0, substr_count($html, 'data-slot-card'));
            $this->assertSame(substr_count($html, 'data-slot-card'), substr_count($html, 'aria-expanded="false"'));
            $this->assertSame(substr_count($html, 'data-slot-card'), substr_count($html, 'class="slot-details"'));
        }

        $bookings = $this->actingAs($student)->get(route('bookings.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-slot-card', $bookings);
        $this->assertStringNotContainsString('slot-toggle', $bookings);
        $this->assertStringContainsString('ауд. 314', $bookings);

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.week\s*\{[^}]*grid-template-columns:\s*repeat\(7,\s*minmax\(0,\s*1fr\)\)/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.week \.day \.slot-date-text\s*\{[^}]*display:\s*none/s',
            $css,
        );

        $js = file_get_contents(public_path('js/tables.js'));
        $this->assertIsString($js);
        $this->assertSame(1, preg_match('/function toggleSlotCard\(card\) \{\n(?<body>.*?)\n  \}/s', $js, $toggle));
        $this->assertStringContainsString("card.querySelector('.slot-details')", $toggle['body']);
        $this->assertStringContainsString('details.hidden = !open', $toggle['body']);
        $this->assertStringNotContainsString('querySelectorAll', $toggle['body']);
        $this->assertSame(1, preg_match('/function bootSlotCards\(\) \{\n(?<body>.*?)\n  \}/s', $js, $boot));
        $this->assertStringContainsString("querySelectorAll('[data-slot-card]')", $boot['body']);
        $this->assertStringContainsString('if (interactive && card.contains(interactive) && interactive !== toggle) {', $boot['body']);
        $this->assertStringContainsString('return;', $boot['body']);
        $this->assertStringContainsString('toggleSlotCard(card);', $boot['body']);
        $this->assertDoesNotMatchRegularExpression('/toggleSlotCard\(\s*document/', $boot['body']);

        $this->assertNotSame($booked->id, $open->id);
    }

    /**
     * @param  array<string, list<string>>  $cards
     */
    private function assertFoldedDay(string $html, string $iso, array $cards, bool $student): void
    {
        $day = $this->dayHtml($html, $iso);
        $this->assertSame(count($cards), preg_match_all('/\bdata-slot-card\b/', $day));
        $ids = [];

        foreach ($cards as $marker => $detailsText) {
            $card = $this->cardHtml($day, $marker);
            [$button, $details, $id] = $this->splitCard($card);
            $this->assertArrayNotHasKey($id, $ids);
            $ids[$id] = $marker;

            $this->assertMatchesRegularExpression(
                '/<button class="slot-toggle" type="button" aria-expanded="false" aria-controls="'.$id.'">/',
                $button,
            );
            $this->assertStringContainsString('class="slot-date-text"', $button);
            $this->assertStringContainsString('class="slot-time-text"', $button);
            $this->assertStringContainsString('свободен', $button);
            $this->assertStringNotContainsString($marker, $button);
            $this->assertStringNotContainsString('class="slot-details"', $button);

            if ($student) {
                $this->assertStringContainsString('class="who-line"', $button);
            } else {
                $this->assertStringNotContainsString('class="who-line"', $button);
            }

            $this->assertMatchesRegularExpression(
                '/<div class="slot-details" id="'.$id.'" hidden>/',
                $details,
            );
            $this->assertStringContainsString($marker, $details);
            $this->assertMatchesRegularExpression('#/slots/\d+/roster#', $details);

            foreach ($detailsText as $text) {
                $this->assertStringContainsString($text, $details);
                $this->assertStringNotContainsString($text, $button);
            }

            if ($student) {
                $this->assertStringContainsString('Иванова А. С.', $button);
                $this->assertStringNotContainsString('доцент', $button);
            }
        }

        $this->assertCount(count($cards), $ids);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function splitCard(string $card): array
    {
        $this->assertSame(1, preg_match('/aria-controls="(slot-details-\d+)"/', $card, $id));
        $parts = preg_split('/(<div class="slot-details")/', $card, 2, PREG_SPLIT_DELIM_CAPTURE);
        $this->assertIsArray($parts);
        $this->assertCount(3, $parts);

        return [$parts[0], $parts[1].$parts[2], $id[1]];
    }

    private function cardHtml(string $html, string $marker): string
    {
        $pattern = '/<article\b[^>]*\bdata-slot-card\b[^>]*>(?:(?!<\/article>).)*'.preg_quote($marker, '/').'(?:(?!<\/article>).)*<\/article>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }

    private function dayHtml(string $html, string $iso): string
    {
        $pattern = '/<section class="day"[^>]*>(?:(?!<\/section>).)*'.preg_quote($iso, '/').'(?:(?!<\/section>).)*<\/section>/s';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return $match[0];
    }

    private function makeSlot(
        User $teacher,
        string $start,
        string $end,
        string $room,
        string $comment,
        ?string $seriesId = null,
    ): Slot {
        return Slot::query()->create([
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'series_id' => $seriesId,
            'starts_at' => $start,
            'ends_at' => $end,
            'building' => 'А',
            'room' => $room,
            'comment' => $comment,
            'is_published' => true,
        ]);
    }
}
