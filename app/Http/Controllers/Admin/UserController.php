<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeacherRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\SearchText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage', User::class);

        $query = (string) $request->query('q', '');
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
            ->each(function (User $user) use ($query) {
                $user->setAttribute('search_text', SearchText::fold($user->name));
                $user->setAttribute('search_match', SearchText::matches($user->name, $query));
            })
            ->values();

        return view('admin.users', [
            'users' => $users,
            'query' => $query,
            'searchHasMatch' => $users->contains(fn (User $user) => (bool) $user->search_match),
        ]);
    }

    public function storeTeacher(StoreTeacherRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'role_id' => Role::idFor($request->string('kind')->toString() === 'student' ? Role::STUDENT : Role::TEACHER),
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'is_active' => true,
            'must_change_password' => true,
        ]);

        if ($user->isStudent()) {
            $user->studentProfile()->create([
                'group_name' => $request->string('group_name')->toString(),
            ]);

            return redirect()->route('admin.users.index')->with('status', 'Студент создан. Передайте временный пароль лично.');
        }

        $user->teacherProfile()->create([
            'position' => $request->string('position')->toString(),
            'department' => $request->string('department')->toString(),
        ]);

        return redirect()->route('admin.users.index')->with('status', 'Преподаватель создан. Передайте временный пароль лично.');
    }

    public function blockForm(User $user): View
    {
        $this->authorize('block', $user);

        return view('admin.block', [
            'user' => $user,
        ]);
    }

    public function block(User $user): RedirectResponse
    {
        $this->authorize('block', $user);
        $user->update(['is_active' => false]);
        $this->endSessions($user);

        return redirect()->route('admin.users.index')->with('status', 'Пользователь заблокирован.');
    }

    public function unblockForm(User $user): View
    {
        $this->authorize('block', $user);

        return view('admin.unblock', [
            'user' => $user,
        ]);
    }

    public function unblock(User $user): RedirectResponse
    {
        $this->authorize('block', $user);
        $user->update(['is_active' => true]);
        $this->endSessions($user);

        return redirect()->route('admin.users.index')->with('status', 'Пользователь разблокирован.');
    }

    public function passwordForm(User $user): View
    {
        $this->authorize('resetPassword', $user);

        return view('admin.password', [
            'user' => $user,
        ]);
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

    public function updateProfile(Request $request, User $user): RedirectResponse
    {
        $this->authorize('updateProfile', $user);

        $request->merge([
            'position' => trim((string) $request->input('position')),
            'department' => trim((string) $request->input('department')),
        ]);

        $validator = Validator::make($request->all(), [
            'position' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
        ], [
            'position.required' => 'Укажите должность.',
            'department.required' => 'Укажите кафедру.',
            'position.max' => 'Должность не длиннее 255 символов.',
            'department.max' => 'Кафедра не длиннее 255 символов.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.users.index')
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        $user->teacherProfile->update([
            'position' => $data['position'],
            'department' => $data['department'],
        ]);

        return redirect()->route('admin.users.index')->with('status', 'Должность и кафедра сохранены.');
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
