<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        if ($user->isStudent()) {
            return $user->studentProfile?->id === $booking->student_profile_id;
        }

        if ($user->isTeacher()) {
            return $booking->slot->teacher_profile_id === $user->teacherProfile?->id;
        }

        return false;
    }

    public function cancel(User $user, Booking $booking): bool
    {
        if (! $user->isStudent()) {
            return false;
        }

        if ($user->studentProfile?->id !== $booking->student_profile_id) {
            return false;
        }

        if ($booking->status !== Booking::BOOKED) {
            return false;
        }

        return $booking->slot->starts_at->isFuture();
    }

    public function cancelAsTeacher(User $user, Booking $booking): bool
    {
        if (! $user->isTeacher()) {
            return false;
        }

        if ($booking->slot->teacher_profile_id !== $user->teacherProfile?->id) {
            return false;
        }

        return $booking->status === Booking::BOOKED;
    }
}
