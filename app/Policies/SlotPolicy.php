<?php

namespace App\Policies;

use App\Models\Slot;
use App\Models\User;

class SlotPolicy
{
    public function view(User $user, Slot $slot): bool
    {
        if ($user->isTeacher()) {
            return $slot->teacher_profile_id === $user->teacherProfile?->id;
        }

        if ($user->isStudent()) {
            return $slot->is_published && $slot->conducted_at === null;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isTeacher();
    }

    public function book(User $user, Slot $slot): bool
    {
        return $user->isStudent()
            && $slot->isFree()
            && (bool) $slot->teacherProfile?->user?->is_active;
    }

    public function unpublish(User $user, Slot $slot): bool
    {
        return $user->isTeacher()
            && $slot->teacher_profile_id === $user->teacherProfile?->id
            && $slot->canBeUnpublished();
    }

    public function conduct(User $user, Slot $slot): bool
    {
        return $user->isTeacher()
            && $slot->teacher_profile_id === $user->teacherProfile?->id
            && $slot->canBeConducted();
    }
}
