<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'slot_id',
    'student_profile_id',
    'status',
    'teacher_comment',
    'cancelled_by_user_id',
    'cancelled_at',
    'completed_at',
])]
class Booking extends Model
{
    public const BOOKED = 'booked';

    public const CANCELLED_BY_STUDENT = 'cancelled_by_student';

    public const CANCELLED_BY_TEACHER = 'cancelled_by_teacher';

    public const COMPLETED = 'completed';

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public static function labelFor(string $status): string
    {
        return match ($status) {
            self::BOOKED => 'занят',
            self::CANCELLED_BY_STUDENT => 'отменён студентом',
            self::CANCELLED_BY_TEACHER => 'отменён преподавателем',
            self::COMPLETED => 'проведён',
            default => $status,
        };
    }

    public function label(): string
    {
        return self::labelFor($this->status);
    }

    public function isFinished(): bool
    {
        return in_array($this->label(), ['проведён', 'снят'], true);
    }

    public function chipClass(): string
    {
        return match ($this->status) {
            self::BOOKED => 'chip-booked',
            self::CANCELLED_BY_STUDENT => 'chip-student',
            self::CANCELLED_BY_TEACHER => 'chip-teacher',
            default => 'chip-done',
        };
    }

    public function isActive(): bool
    {
        return $this->status === self::BOOKED;
    }
}
