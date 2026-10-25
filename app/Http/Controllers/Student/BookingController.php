<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Slot;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user()->studentProfile;
        $bookings = Booking::query()
            ->with(['slot.teacherProfile.user'])
            ->where('student_profile_id', $student->id)
            ->get()
            ->sortByDesc(fn (Booking $booking) => $booking->slot->starts_at->getTimestamp())
            ->values();

        return view('student.bookings', [
            'bookings' => $bookings,
        ]);
    }

    public function create(Request $request, Slot $slot): View
    {
        $this->authorize('view', $slot);
        $slot->load(['teacherProfile.user', 'bookings.studentProfile.user']);
        $mine = $slot->bookings->first(
            fn (Booking $booking) => $booking->student_profile_id === $request->user()->studentProfile->id
                && $booking->isActive(),
        );

        return view('student.confirm', [
            'slot' => $slot,
            'mine' => $mine,
        ]);
    }

    public function store(Request $request, Slot $slot, BookingService $bookings): RedirectResponse
    {
        $this->authorize('book', $slot);

        $booking = $bookings->book($slot, $request->user()->studentProfile);
        $message = $booking->wasRecentlyCreated
            ? 'Запись сохранена. Слот для остальных открыт.'
            : 'Эта запись уже есть.';

        return redirect()->route('bookings.index')->with('status', $message);
    }

    public function show(Booking $booking): View
    {
        $this->authorize('view', $booking);
        $booking->load(['slot.teacherProfile.user']);

        return view('student.booking', [
            'booking' => $booking,
        ]);
    }

    public function cancelForm(Booking $booking): View
    {
        $this->authorize('cancel', $booking);
        $booking->load(['slot.teacherProfile.user']);

        return view('student.cancel', [
            'booking' => $booking,
        ]);
    }

    public function cancel(Booking $booking, BookingService $bookings, Request $request): RedirectResponse
    {
        $this->authorize('cancel', $booking);
        $bookings->cancelByStudent($booking, $request->user());

        return redirect()->route('bookings.index')->with('status', 'Запись отменена. Слот для остальных открыт.');
    }
}
