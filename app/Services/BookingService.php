<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Slot;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{created: Collection<int, Slot>, skipped: int}
     */
    public function publish(TeacherProfile $teacher, array $attributes, ?Carbon $repeatUntil): array
    {
        return DB::transaction(function () use ($teacher, $attributes, $repeatUntil) {
            TeacherProfile::query()->whereKey($teacher->id)->lockForUpdate()->first();

            $seriesId = $repeatUntil === null ? null : (string) Str::uuid();
            $startClock = $attributes['starts_at']->format('H:i:s');
            $endClock = $attributes['ends_at']->format('H:i:s');
            $cursor = $attributes['starts_at']->copy()->startOfDay();
            $last = ($repeatUntil ?? $attributes['starts_at'])->copy()->startOfDay();
            $created = collect();
            $skipped = 0;

            while ($cursor->lessThanOrEqualTo($last)) {
                $starts = Carbon::parse($cursor->toDateString().' '.$startClock);
                $ends = Carbon::parse($cursor->toDateString().' '.$endClock);

                $past = ! $starts->greaterThan(now());
                $overlap = ! $past && Slot::teacherHasOverlap($teacher->id, $starts, $ends);

                if ($overlap) {
                    $skipped++;
                } elseif (! $past) {
                    $created->push(Slot::query()->create([
                        'teacher_profile_id' => $teacher->id,
                        'series_id' => $seriesId,
                        'starts_at' => $starts,
                        'ends_at' => $ends,
                        'building' => $attributes['building'],
                        'room' => $attributes['room'],
                        'comment' => $attributes['comment'],
                        'meeting_url' => $attributes['meeting_url'],
                        'is_published' => true,
                    ]));
                }

                if ($repeatUntil === null) {
                    break;
                }

                $cursor->addWeek();
            }

            if ($created->isEmpty()) {
                throw ValidationException::withMessages([
                    'start_time' => $skipped > 0
                        ? 'Это время пересекается с другим вашим слотом.'
                        : 'Начало должно быть в будущем.',
                ]);
            }

            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    public function stopSeries(Slot $slot): void
    {
        if ($slot->series_id === null) {
            throw ValidationException::withMessages([
                'slot' => 'У этого слота нет повтора.',
            ]);
        }

        DB::transaction(function () use ($slot) {
            $future = Slot::query()
                ->where('series_id', $slot->series_id)
                ->where('teacher_profile_id', $slot->teacher_profile_id)
                ->where('is_published', true)
                ->whereNull('conducted_at')
                ->where('starts_at', '>', now())
                ->lockForUpdate()
                ->get();

            foreach ($future as $item) {
                $active = Booking::query()
                    ->where('slot_id', $item->id)
                    ->where('status', Booking::BOOKED)
                    ->lockForUpdate()
                    ->exists();

                if ($active) {
                    continue;
                }

                $item->update(['is_published' => false]);
            }
        });
    }

    /**
     * @param  iterable<int, int|string>  $teacherProfileIds
     */
    public function completeEndedSlots(iterable $teacherProfileIds): void
    {
        $ids = collect($teacherProfileIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $slots = Slot::query()
            ->whereIn('teacher_profile_id', $ids)
            ->where('is_published', true)
            ->whereNull('conducted_at')
            ->where('ends_at', '<', now())
            ->get();

        foreach ($slots as $slot) {
            $this->markEnded($slot);
        }
    }

    private function markEnded(Slot $slot): void
    {
        DB::transaction(function () use ($slot) {
            $locked = Slot::query()->whereKey($slot->id)->lockForUpdate()->first();

            if ($locked === null || ! $locked->is_published || $locked->conducted_at !== null || ! $locked->ends_at->lt(now())) {
                return;
            }

            $bookings = Booking::query()
                ->where('slot_id', $locked->id)
                ->where('status', Booking::BOOKED)
                ->lockForUpdate()
                ->get();

            foreach ($bookings as $booking) {
                $booking->update([
                    'status' => Booking::COMPLETED,
                    'completed_at' => $locked->ends_at,
                ]);
            }

            $locked->update(['conducted_at' => $locked->ends_at]);
        });
    }
}
