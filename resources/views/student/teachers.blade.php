@extends('layouts.app')

@section('title', 'Преподаватели')

@section('content')
  <div class="page-head">
    <h1>Преподаватели</h1>
  </div>
  @include('partials.errors')
  <div class="field directory-search">
    <label for="teacher-search">Поиск</label>
    <input id="teacher-search" type="search" placeholder="Имя или кафедра" data-teacher-search autocomplete="off">
  </div>
  <div class="table-wrap person-cards">
    <table class="data-table" data-teacher-directory>
      <thead>
        <tr>
          <th>Преподаватель</th>
          <th>Должность</th>
          <th>Кафедра</th>
          <th data-skip>Избранное</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($teachers as $teacher)
          <tr class="person-card" data-search="{{ $teacher->user->name }} {{ $teacher->department }}">
            <td class="person-name">{{ $teacher->user->name }}</td>
            <td class="person-fact">{{ $teacher->position }}</td>
            <td class="person-extra">{{ $teacher->department }}</td>
            <td class="person-actions">
              @if ($favoriteIds->contains($teacher->id))
                <form class="fav-form" method="POST" action="{{ route('teachers.unfavorite', $teacher) }}">
                  @csrf
                  <span class="fav-on">в избранном</span>
                  <button class="btn btn-small btn-ghost" type="submit">Убрать</button>
                </form>
              @else
                <form method="POST" action="{{ route('teachers.favorite', $teacher) }}">
                  @csrf
                  <button class="btn btn-small" type="submit">В избранное</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr data-static>
            <td colspan="4" class="muted">Преподавателей пока нет.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
