@extends('layouts.app')

@section('title', 'Преподаватели')

@section('content')
  <div class="page-head">
    <div>
      <h1>Преподаватели</h1>
      <p class="lead">Все преподаватели кафедры. На главную попадают только избранные.</p>
    </div>
  </div>
  @include('partials.errors')
  <div class="field directory-search">
    <label for="teacher-search">Поиск</label>
    <input id="teacher-search" type="search" placeholder="Имя или кафедра" data-teacher-search autocomplete="off">
  </div>
  <div class="table-wrap">
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
          <tr data-search="{{ $teacher->user->name }} {{ $teacher->department }}">
            <td>{{ $teacher->user->name }}</td>
            <td>{{ $teacher->position }}</td>
            <td>{{ $teacher->department }}</td>
            <td>
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
