<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSlotRequest;
use App\Models\Booking;
use App\Models\Slot;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SlotController extends Controller
{
    public function index(Request $request, BookingService $bookings): View
    {
        $profile = $request->user()->teacherProfile;
        $bookings->completeEndedSlots([$profile->id]);

        $slots = $profile->slots()
            ->with(['bookings.studentProfile.user'])
            ->orderByDesc('starts_at')
            ->get();

        $seriesIds = $slots->pluck('series_id')->filter()->unique()->values();
        $stoppableSeries = $seriesIds->isEmpty()
            ? collect()
            : Slot::query()
                ->whereIn('series_id', $seriesIds)
                ->where('is_published', true)
                ->whereNull('conducted_at')
                ->where('starts_at', '>', now())
                ->whereDoesntHave('bookings', function ($query) {
                    $query->where('status', Booking::BOOKED);
                })
                ->pluck('series_id')
                ->unique()
                ->values();

        return view('teacher.slots', [
            'slots' => $slots,
            'stoppableSeries' => $stoppableSeries,
        ]);
    }

    public function roster(Request $request, Slot $slot): View
    {
        $this->authorize('viewRoster', $slot);
        $slot->load(['bookings.studentProfile.user', 'teacherProfile.user']);

        return view('slots.roster', [
            'slot' => $slot,
            'signups' => $slot->sortedBookings(),
            'teacherActions' => $request->user()->isTeacher()
                && $slot->teacher_profile_id === $request->user()->teacherProfile?->id,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Slot::class);

        return view('teacher.create');
    }

    public function store(StoreSlotRequest $request, BookingService $bookings): RedirectResponse
    {
        $result = $bookings->publish(
            $request->user()->teacherProfile,
            $request->slotAttributes(),
            $request->repeatUntil(),
        );

        $count = $result['created']->count();
        $series = $result['created']->first()?->series_id !== null;

        if (! $series) {
            $message = 'Слот опубликован.';
        } elseif ($result['skipped'] > 0) {
            $message = 'Опубликовано слотов: '.$count.'. Дни с пересечением пропущены.';
        } else {
            $message = 'Опубликовано слотов: '.$count.'.';
        }

        return redirect()->route('teacher.slots.index')->with('status', $message);
    }

    public function stopSeriesForm(Slot $slot): View
    {
        $this->authorize('stopSeries', $slot);

        return view('teacher.stop-series', [
            'slot' => $slot,
        ]);
    }

    public function stopSeries(Slot $slot, BookingService $bookings): RedirectResponse
    {
        $this->authorize('stopSeries', $slot);
        $bookings->stopSeries($slot);

        return redirect()->route('teacher.slots.index')->with('status', 'Повтор остановлен. Будущие пустые слоты сняты с публикации.');
    }

    public function unpublishForm(Slot $slot): View
    {
        $this->authorize('unpublish', $slot);

        return view('teacher.unpublish', [
            'slot' => $slot,
        ]);
    }

    public function unpublish(Slot $slot, BookingService $bookings): RedirectResponse
    {
        $this->authorize('unpublish', $slot);
        $bookings->unpublish($slot);

        return redirect()->route('teacher.slots.index')->with('status', 'Слот снят с публикации.');
    }

    public function conductForm(Slot $slot): View
    {
        $this->authorize('conduct', $slot);
        $slot->load(['bookings.studentProfile.user']);

        return view('teacher.conduct', [
            'slot' => $slot,
        ]);
    }

    public function conduct(Request $request, Slot $slot, BookingService $bookings): RedirectResponse
    {
        $this->authorize('conduct', $slot);
        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:500'],
        ], [
            'comment.max' => 'Комментарий не длиннее 500 символов.',
        ]);

        $comment = trim((string) ($data['comment'] ?? ''));
        $bookings->conduct($slot, $comment === '' ? null : $comment);

        return redirect()->route('teacher.slots.index')->with('status', 'Консультация отмечена проведённой.');
    }
}
