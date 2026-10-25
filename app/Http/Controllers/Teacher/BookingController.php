<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function cancelForm(Booking $booking): View
    {
        $this->authorize('cancelAsTeacher', $booking);
        $booking->load(['slot', 'studentProfile.user']);

        return view('teacher.cancel', [
            'booking' => $booking,
        ]);
    }

    public function cancel(Request $request, Booking $booking, BookingService $bookings): RedirectResponse
    {
        $this->authorize('cancelAsTeacher', $booking);
        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:500'],
        ], [
            'comment.max' => 'Комментарий не длиннее 500 символов.',
        ]);

        $comment = trim((string) ($data['comment'] ?? ''));
        $bookings->cancelByTeacher($booking, $request->user(), $comment === '' ? null : $comment);

        return redirect()->route('teacher.slots.index')->with('status', 'Запись студента отменена. Слот для остальных открыт.');
    }
}
