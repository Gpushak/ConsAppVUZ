<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeacherRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage', User::class);

        $collator = new \Collator('ru_RU');
        $users = User::query()
            ->with(['role', 'studentProfile', 'teacherProfile'])
            ->where('id', '!=', $request->user()->id)
            ->get()
            ->sort(function (User $left, User $right) use ($collator) {
                $role = $this->roleOrder($left) <=> $this->roleOrder($right);

                if ($role !== 0) {
                    return $role;
                }

                return $collator->compare($left->name, $right->name) ?: 0;
            })
            ->values();

        return view('admin.users', [
            'users' => $users,
        ]);
    }

    public function storeTeacher(StoreTeacherRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'role_id' => Role::idFor(Role::TEACHER),
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $user->teacherProfile()->create([
            'position' => $request->string('position')->toString(),
            'department' => $request->string('department')->toString(),
        ]);

        return redirect()->route('admin.users.index')->with('status', 'Преподаватель создан. Передайте временный пароль лично.');
    }

    public function block(User $user): RedirectResponse
    {
        $this->authorize('block', $user);
        $user->update(['is_active' => false]);
        $this->endSessions($user);

        return redirect()->route('admin.users.index')->with('status', 'Пользователь заблокирован.');
    }

    public function unblock(User $user): RedirectResponse
    {
        $this->authorize('block', $user);
        $user->update(['is_active' => true]);
        $this->endSessions($user);

        return redirect()->route('admin.users.index')->with('status', 'Пользователь разблокирован.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ], [
            'password.required' => 'Укажите временный пароль.',
            'password.min' => 'Пароль не короче 8 символов.',
        ]);

        $user->update([
            'password' => $data['password'],
            'must_change_password' => true,
        ]);
        $this->endSessions($user);

        return redirect()->route('admin.users.index')->with('status', 'Временный пароль задан. Пользователь сменит его при входе.');
    }

    private function endSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function roleOrder(User $user): int
    {
        return match ($user->role?->slug) {
            Role::TEACHER => 0,
            Role::STUDENT => 1,
            default => 2,
        };
    }
}
