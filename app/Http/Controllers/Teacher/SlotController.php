<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSlotRequest;
use App\Http\Requests\UpdateSlotRequest;
use App\Models\Booking;
use App\Models\Slot;
use App\Services\BookingService;
use App\Support\DateSpan;
use App\Support\Ru;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SlotController extends Controller
{
    public function index(Request $request, BookingService $bookings): View
    {
        $profile = $request->user()->teacherProfile;
        $bookings->completeEndedSlots([$profile->id]);

        $span = DateSpan::fromQuery($request->query('from'), $request->query('to'));
        $slots = $profile->slots()
            ->with(['bookings.studentProfile.user'])
            ->orderBy('starts_at')
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

        $stopSlotIds = $slots
            ->filter(function (Slot $slot) use ($stoppableSeries) {
                return $slot->series_id !== null
                    && $stoppableSeries->contains($slot->series_id)
                    && $slot->is_published
                    && $slot->conducted_at === null
                    && $slot->starts_at->isFuture();
            })
            ->groupBy('series_id')
            ->map(fn ($group) => $group->sortBy(fn (Slot $slot) => $slot->starts_at->getTimestamp())->first()->id)
            ->values();

        $seriesAhead = $slots
            ->filter(fn (Slot $slot) => $slot->series_id !== null
                && $slot->is_published
                && $slot->conducted_at === null
                && $slot->starts_at->isFuture())
            ->groupBy('series_id')
            ->mapWithKeys(function ($group) {
                $nearest = $group->sortBy(fn (Slot $slot) => $slot->starts_at->getTimestamp())->first();
                $later = $group->filter(fn (Slot $slot) => $slot->starts_at->greaterThan($nearest->starts_at))->count();

                return [$nearest->id => $later];
            });

        $listed = $slots->reject(fn (Slot $slot) => $slot->isFinished())->values();
        $visible = $span->bounded
            ? $listed->filter(fn (Slot $slot) => $span->contains($slot->starts_at))->values()
            : $listed;

        return view('teacher.slots', [
            'slots' => $listed,
            'days' => $span->buckets($visible),
            'span' => $span,
            'stopSlotIds' => $stopSlotIds,
            'seriesAhead' => $seriesAhead,
            'showFinishedToggle' => false,
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

    public function edit(Slot $slot): View
    {
        $this->authorize('update', $slot);

        return view('teacher.edit', [
            'slot' => $slot,
        ]);
    }

    public function update(UpdateSlotRequest $request, Slot $slot, BookingService $bookings): RedirectResponse
    {
        $apply = $request->applyToSeries();
        $result = $bookings->updateSlot($slot, $request->slotAttributes(), $apply);

        return redirect()->route('teacher.slots.index')->with('status', $this->slotUpdatedMessage($apply, $result));
    }

    /**
     * @param  array{changed: int, skipped: int}  $result
     */
    private function slotUpdatedMessage(bool $apply, array $result): string
    {
        if (! $apply) {
            return 'Слот изменён.';
        }

        $message = 'Слот изменён. '.Ru::seriesDaysWillChange($result['changed']);

        if ($result['skipped'] > 0) {
            $message .= ' Дни с пересечением пропущены.';
        }

        return $message;
    }

    public function stopSeriesForm(Slot $slot): View
    {
        $this->authorize('stopSeries', $slot);

        $counts = $slot->futureSeriesCounts();

        return view('teacher.stop-series', [
            'slot' => $slot,
            'remaining' => $counts['remaining'],
            'empty' => $counts['empty'],
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
