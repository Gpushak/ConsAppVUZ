<?php

namespace App\Http\Controllers;

use App\Models\Slot;
use App\Models\TeacherProfile;
use App\Services\BookingService;
use App\Support\DateSpan;
use App\Support\MonthGrid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request, BookingService $bookings): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isTeacher()) {
            return redirect()->route('teacher.slots.index');
        }

        if ($user->isAdmin()) {
            return redirect()->route('admin.users.index');
        }

        $student = $user->studentProfile;
        $collator = new \Collator('ru_RU');
        $favorites = $student->favoriteTeachers()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sort(fn ($left, $right) => $collator->compare($left->user->name, $right->user->name) ?: 0)
            ->values();

        $bookings->completeEndedSlots($favorites->pluck('id'));

        $span = DateSpan::fromQuery($request->query('from'), $request->query('to'));
        $month = MonthGrid::parse($request->query('month'));
        $selected = $this->selectedFavorite($favorites, $request->query('teacher'));
        $slots = collect();

        if ($favorites->isNotEmpty()) {
            $slots = Slot::query()
                ->with(['teacherProfile.user', 'bookings.studentProfile.user'])
                ->whereIn('teacher_profile_id', $favorites->pluck('id'))
                ->where('is_published', true)
                ->orderBy('starts_at')
                ->orderBy('id')
                ->get();
        }

        $open = $slots
            ->filter(fn (Slot $slot) => $slot->conducted_at === null && $slot->starts_at->isFuture())
            ->values();
        $visible = $span->bounded
            ? $open->filter(fn (Slot $slot) => $span->contains($slot->starts_at))->values()
            : $open;
        $scope = $selected === null ? $slots : $slots->where('teacher_profile_id', $selected->id);
        $marked = [];

        foreach ($scope as $slot) {
            $marked[$slot->localDate()] = true;
        }

        $activeDay = $this->selectedDay($request->query('day'), $marked);
        $daySlots = $activeDay === null
            ? null
            : $scope->filter(fn (Slot $slot) => $slot->localDate() === $activeDay->toDateString())->values();
        $nearest = $selected === null
            ? null
            : $open->first(fn (Slot $slot) => $slot->teacher_profile_id === $selected->id);
        $teacherId = $selected?->id;
        $dayKey = $activeDay?->toDateString();

        return view('student.home', [
            'favorites' => $favorites,
            'student' => $student,
            'span' => $span,
            'days' => $span->buckets($visible),
            'hasOpen' => $open->isNotEmpty(),
            'selectedTeacher' => $selected,
            'nearest' => $nearest,
            'activeDay' => $activeDay,
            'daySlots' => $daySlots,
            'nearestQuery' => $this->homeQuery($span, $month, $teacherId),
            'month' => $month,
            'monthTitle' => MonthGrid::title($month),
            'weeks' => MonthGrid::weeks($month, $marked),
            'weekLinks' => [
                'previous' => $this->homeQuery($span->previous(), $month, $teacherId, $dayKey),
                'next' => $this->homeQuery($span->next(), $month, $teacherId, $dayKey),
            ],
            'monthLinks' => [
                'previous' => $this->homeQuery($span, $month->copy()->subMonth(), $teacherId, $dayKey),
                'next' => $this->homeQuery($span, $month->copy()->addMonth(), $teacherId, $dayKey),
            ],
        ]);
    }

    /**
     * @param  Collection<int, TeacherProfile>  $favorites
     */
    private function selectedFavorite(Collection $favorites, mixed $teacher): ?TeacherProfile
    {
        if (! is_string($teacher) || preg_match('/^[1-9]\d*$/', $teacher) !== 1) {
            return null;
        }

        $match = $favorites->firstWhere('id', (int) $teacher);

        return $match instanceof TeacherProfile ? $match : null;
    }

    /**
     * A day replaces the nearest consultation only when that day is marked.
     *
     * @param  array<string, true>  $marked
     */
    private function selectedDay(mixed $day, array $marked): ?Carbon
    {
        $date = DateSpan::parseDay($day);

        if ($date === null || ! isset($marked[$date->toDateString()])) {
            return null;
        }

        return $date;
    }

    /**
     * @return array<string, int|string>
     */
    private function homeQuery(DateSpan $span, Carbon $month, ?int $teacherId, ?string $day = null): array
    {
        $query = $span->query();
        $query['month'] = $month->copy()->timezone(DateSpan::TIMEZONE)->format('Y-m');

        if ($teacherId !== null) {
            $query['teacher'] = $teacherId;
        }

        if ($day !== null) {
            $query['day'] = $day;
        }

        return $query;
    }
}
