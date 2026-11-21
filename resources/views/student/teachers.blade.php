@extends('layouts.app')

@section('title', 'Преподаватели')

@section('content')
  <div class="page-head">
    <h1>Преподаватели</h1>
    <p class="page-links">
      <a href="{{ route('home') }}">Слоты избранных</a>
    </p>
  </div>
  @include('partials.errors')
  @php
    $favoriteCount = $teachers->filter(fn ($teacher) => $favoriteIds->contains($teacher->id))->count();
  @endphp
  <p class="meta">
    @if ($favoriteCount === 0)
      Избранных нет. Добавьте преподавателя, чтобы его слоты были на главной.
    @else
      В избранном {{ $favoriteCount }} — их слоты на главной.
    @endif
  </p>
  <form class="field directory-search" method="GET" action="{{ route('teachers.index') }}">
    <label for="teacher-search">Поиск</label>
    <input id="teacher-search" type="search" name="q" value="{{ $query }}" placeholder="Имя, должность или кафедра" data-teacher-search autocomplete="off">
    <p id="teacher-search-status" class="visually-hidden" aria-live="polite"></p>
  </form>
  <div class="table-wrap person-cards teacher-directory">
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
          <tr class="person-card" data-search="{{ $teacher->search_text }}" @if ($favoriteIds->contains($teacher->id)) data-favorite @endif @unless ($teacher->search_match) hidden @endunless>
            <td class="person-name">{{ $teacher->user->name }}</td>
            <td class="person-fact"><span class="cell-label">Должность</span>{{ $teacher->position }}</td>
            <td class="person-dept"><span class="cell-label">Кафедра</span>{{ $teacher->department }}</td>
            <td class="person-actions">
              @if ($favoriteIds->contains($teacher->id))
                <form class="fav-form" method="POST" action="{{ route('teachers.unfavorite', $teacher) }}">
                  @csrf
                  <span class="chip chip-free">В избранном</span>
                  <button class="btn btn-small btn-ghost" type="submit">Убрать из избранного</button>
                </form>
              @else
                <form class="fav-form" method="POST" action="{{ route('teachers.favorite', $teacher) }}">
                  @csrf
                  <button class="btn btn-small" type="submit">Добавить в избранное</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr data-static>
            <td colspan="4" class="muted">Преподавателей пока нет.</td>
          </tr>
        @endforelse
        @if ($teachers->isNotEmpty())
          <tr data-static data-nomatch @if ($searchHasMatch) hidden @endif>
            <td colspan="4" class="muted">Нет совпадений</td>
          </tr>
        @endif
      </tbody>
    </table>
  </div>
@endsection
