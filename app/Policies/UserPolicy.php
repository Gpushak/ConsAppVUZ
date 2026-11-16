<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }

    public function block(User $user, User $target): bool
    {
        return $user->isAdmin()
            && $user->id !== $target->id
            && ! $target->isAdmin();
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->isAdmin() && $user->id !== $target->id;
    }

    public function updateProfile(User $user, User $target): bool
    {
        return $user->isAdmin()
            && $user->id !== $target->id
            && $target->isTeacher()
            && $target->teacherProfile !== null;
    }
}
