<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Slot;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function book(Slot $slot, StudentProfile $student): Booking
    {
        try {
            return DB::transaction(function () use ($slot, $student) {
                $lockedSlot = Slot::query()->whereKey($slot->id)->lockForUpdate()->firstOrFail();
                StudentProfile::query()->whereKey($student->id)->lockForUpdate()->first();

                if (! $lockedSlot->canBeBooked() || ! $lockedSlot->teacherProfile?->user?->is_active) {
                    throw ValidationException::withMessages([
                        'slot' => 'На этот слот уже нельзя записаться.',
                    ]);
                }

                $active = Booking::query()
                    ->where('student_profile_id', $student->id)
                    ->where('status', Booking::BOOKED)
                    ->lockForUpdate()
                    ->get();

                $existing = $active->firstWhere('slot_id', $lockedSlot->id);

                if ($existing) {
                    return $existing;
                }

                $overlap = Slot::query()
                    ->whereIn('id', $active->pluck('slot_id'))
                    ->where('starts_at', '<', $lockedSlot->ends_at)
                    ->where('ends_at', '>', $lockedSlot->starts_at)
                    ->exists();

                if ($overlap) {
                    throw ValidationException::withMessages([
                        'slot' => 'У вас уже есть запись на пересекающееся время.',
                    ]);
                }

                return Booking::query()->create([
                    'slot_id' => $lockedSlot->id,
                    'student_profile_id' => $student->id,
                    'status' => Booking::BOOKED,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Booking::query()
                ->where('slot_id', $slot->id)
                ->where('student_profile_id', $student->id)
                ->where('status', Booking::BOOKED)
                ->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function cancelByStudent(Booking $booking, User $actor): void
    {
        DB::transaction(function () use ($booking, $actor) {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $slot = Slot::query()->whereKey($locked->slot_id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Booking::BOOKED || ! $slot->starts_at->isFuture()) {
                throw ValidationException::withMessages([
                    'booking' => 'Эту запись уже нельзя отменить.',
                ]);
            }

            $locked->update([
                'status' => Booking::CANCELLED_BY_STUDENT,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
            ]);
        });
    }

    public function cancelByTeacher(Booking $booking, User $actor, ?string $comment): void
    {
        DB::transaction(function () use ($booking, $actor, $comment) {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Booking::BOOKED) {
                throw ValidationException::withMessages([
                    'booking' => 'Эту запись уже нельзя отменить.',
                ]);
            }

            $locked->update([
                'status' => Booking::CANCELLED_BY_TEACHER,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
                'teacher_comment' => $comment,
            ]);
        });
    }

    public function conduct(Slot $slot, ?string $comment): void
    {
        DB::transaction(function () use ($slot, $comment) {
            $locked = Slot::query()->whereKey($slot->id)->lockForUpdate()->firstOrFail();

            if ($locked->conducted_at !== null || $locked->starts_at->isFuture()) {
                throw ValidationException::withMessages([
                    'slot' => 'Отметить проведённой можно только после начала.',
                ]);
            }

            $bookings = Booking::query()
                ->where('slot_id', $locked->id)
                ->where('status', Booking::BOOKED)
                ->lockForUpdate()
                ->get();

            if ($bookings->isEmpty()) {
                throw ValidationException::withMessages([
                    'slot' => 'Нет активных записей.',
                ]);
            }

            foreach ($bookings as $booking) {
                $booking->update([
                    'status' => Booking::COMPLETED,
                    'completed_at' => now(),
                    'teacher_comment' => $comment !== null && $comment !== ''
                        ? $comment
                        : $booking->teacher_comment,
                ]);
            }

            $locked->update(['conducted_at' => now()]);
        });
    }

    public function unpublish(Slot $slot): void
    {
        DB::transaction(function () use ($slot) {
            $locked = Slot::query()->whereKey($slot->id)->lockForUpdate()->firstOrFail();
            $active = Booking::query()
                ->where('slot_id', $locked->id)
                ->where('status', Booking::BOOKED)
                ->lockForUpdate()
                ->exists();

            if ($active || ! $locked->is_published) {
                throw ValidationException::withMessages([
                    'slot' => 'Слот можно снять, только пока на него никто не записан.',
                ]);
            }

            $locked->update(['is_published' => false]);
        });
    }
}
