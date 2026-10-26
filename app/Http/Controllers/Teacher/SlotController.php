<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSlotRequest;
use App\Models\Slot;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SlotController extends Controller
{
    public function index(Request $request): View
    {
        $slots = $request->user()->teacherProfile->slots()
            ->with(['bookings.studentProfile.user'])
            ->orderByDesc('starts_at')
            ->get();

        return view('teacher.slots', [
            'slots' => $slots,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Slot::class);

        return view('teacher.create');
    }

    public function store(StoreSlotRequest $request): RedirectResponse
    {
        $request->user()->teacherProfile->slots()->create($request->slotAttributes());

        return redirect()->route('teacher.slots.index')->with('status', 'Слот опубликован.');
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
