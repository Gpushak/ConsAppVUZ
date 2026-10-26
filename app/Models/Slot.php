<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'teacher_profile_id',
    'starts_at',
    'ends_at',
    'building',
    'room',
    'comment',
    'meeting_url',
    'is_published',
    'conducted_at',
])]
class Slot extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'conducted_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isOnline(): bool
    {
        return $this->building === null || $this->building === '';
    }

    public function place(): string
    {
        if ($this->isOnline()) {
            return (string) $this->meeting_url;
        }

        return $this->building.', ауд. '.$this->room;
    }

    public function dateLabel(): string
    {
        return $this->starts_at->format('d.m.Y');
    }

    public function timeRange(): string
    {
        return $this->starts_at->format('H:i').'–'.$this->ends_at->format('H:i');
    }

    public function isFree(): bool
    {
        return $this->is_published
            && $this->conducted_at === null
            && $this->starts_at->greaterThan(now());
    }

    public function hasMeetingLink(): bool
    {
        return is_string($this->meeting_url)
            && preg_match('/^https?:\/\//i', $this->meeting_url) === 1;
    }

    public function activeBookingsCount(): int
    {
        if ($this->relationLoaded('bookings')) {
            return $this->bookings->where('status', Booking::BOOKED)->count();
        }

        return $this->bookings()->where('status', Booking::BOOKED)->count();
    }

    public function canBeConducted(): bool
    {
        return $this->conducted_at === null
            && ! $this->starts_at->isFuture()
            && $this->activeBookingsCount() > 0;
    }

    public function canBeUnpublished(): bool
    {
        if (! $this->is_published || $this->conducted_at !== null) {
            return false;
        }

        return $this->activeBookingsCount() === 0;
    }

    public function statusLabel(): string
    {
        if ($this->conducted_at !== null) {
            return 'проведён';
        }

        if (! $this->is_published) {
            return 'снят';
        }

        if ($this->starts_at->isFuture()) {
            return 'свободен';
        }

        $statuses = $this->bookings->pluck('status')->unique()->values();

        if ($statuses->contains(Booking::BOOKED)) {
            return 'занят';
        }

        if ($statuses->count() === 1) {
            return Booking::labelFor((string) $statuses->first());
        }

        return 'проведён';
    }

    public function chipClass(): string
    {
        return match ($this->statusLabel()) {
            'свободен' => 'chip-free',
            'занят' => 'chip-booked',
            'отменён студентом' => 'chip-student',
            'отменён преподавателем' => 'chip-teacher',
            default => 'chip-done',
        };
    }

    /**
     * @return Collection<string, Collection<int, Booking>>
     */
    public function groupedBookings(bool $activeOnly = false): Collection
    {
        $bookings = $this->bookings;

        if ($activeOnly) {
            $bookings = $bookings->where('status', Booking::BOOKED)->values();
        }

        $collator = new \Collator('ru_RU');
        $sorted = $bookings->sort(function (Booking $left, Booking $right) use ($collator) {
            $group = $collator->compare(
                $left->studentProfile->group_name,
                $right->studentProfile->group_name,
            );

            if ($group !== 0 && $group !== false) {
                return $group;
            }

            $name = $collator->compare(
                $left->studentProfile->user->name,
                $right->studentProfile->user->name,
            );

            return $name === false ? 0 : $name;
        })->values();

        return $sorted->groupBy(fn (Booking $booking) => $booking->studentProfile->group_name);
    }
}
