<?php

namespace App\Http\Controllers;

use App\Models\Slot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isTeacher()) {
            return redirect()->route('teacher.slots.index');
        }

        if ($user->isAdmin()) {
            return redirect()->route('admin.users.index');
        }

        $student = $user->studentProfile;
        $collator = new \Collator('ru_RU');
        $favorites = $student->favoriteTeachers()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sort(fn ($left, $right) => $collator->compare($left->user->name, $right->user->name) ?: 0)
            ->values();

        $slotsByTeacher = collect();

        if ($favorites->isNotEmpty()) {
            $slotsByTeacher = Slot::query()
                ->with(['teacherProfile.user', 'bookings.studentProfile.user'])
                ->whereIn('teacher_profile_id', $favorites->pluck('id'))
                ->where('is_published', true)
                ->whereNull('conducted_at')
                ->where('starts_at', '>', now())
                ->orderBy('starts_at')
                ->get()
                ->groupBy('teacher_profile_id');
        }

        return view('student.home', [
            'favorites' => $favorites,
            'slotsByTeacher' => $slotsByTeacher,
            'student' => $student,
        ]);
    }
}
