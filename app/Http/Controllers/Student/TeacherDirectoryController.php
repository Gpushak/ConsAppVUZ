<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\TeacherProfile;
use App\Support\SearchText;
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
        $query = (string) $request->query('q', '');
        $teachers = TeacherProfile::query()
            ->with('user')
            ->whereHas('user', fn ($builder) => $builder->where('is_active', true))
            ->get()
            ->each(function (TeacherProfile $teacher) use ($query) {
                $teacher->setAttribute('search_text', SearchText::fold(implode(' ', [
                    $teacher->user->name,
                    $teacher->position,
                    $teacher->department,
                ])));
                $teacher->setAttribute('search_match', SearchText::matches($teacher->search_text, $query));
            })
            ->sort(function (TeacherProfile $left, TeacherProfile $right) use ($collator, $favoriteIds) {
                $favorite = ((int) $favoriteIds->contains($right->id)) <=> ((int) $favoriteIds->contains($left->id));
                if ($favorite !== 0) {
                    return $favorite;
                }

                return $collator->compare($left->user->name, $right->user->name) ?: 0;
            })
            ->values();

        return view('student.teachers', [
            'teachers' => $teachers,
            'favoriteIds' => $favoriteIds,
            'query' => $query,
            'searchHasMatch' => $teachers->contains(fn (TeacherProfile $teacher) => (bool) $teacher->search_match),
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
