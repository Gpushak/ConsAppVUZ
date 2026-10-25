<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\TeacherProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user()->studentProfile;
        $collator = new \Collator('ru_RU');
        $favoriteIds = $student->favoriteTeachers()->pluck('teacher_profiles.id');
        $teachers = TeacherProfile::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sort(fn ($left, $right) => $collator->compare($left->user->name, $right->user->name) ?: 0)
            ->values();

        return view('student.teachers', [
            'teachers' => $teachers,
            'favoriteIds' => $favoriteIds,
        ]);
    }

    public function favorite(Request $request, TeacherProfile $teacher): RedirectResponse
    {
        abort_unless($teacher->user?->is_active, 404);

        $request->user()->studentProfile->favoriteTeachers()->syncWithoutDetaching([$teacher->id]);

        return redirect()->route('teachers.index')->with('status', 'Преподаватель добавлен в избранное.');
    }

    public function unfavorite(Request $request, TeacherProfile $teacher): RedirectResponse
    {
        $request->user()->studentProfile->favoriteTeachers()->detach($teacher->id);

        return redirect()->route('teachers.index')->with('status', 'Преподаватель убран из избранного.');
    }
}
