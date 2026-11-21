@extends('layouts.app')

@section('title', 'Пользователи')

@php
  $creating = old('form') === 'teacher';
  $profileId = (string) old('profile_user');
  $inlineErrors = [];
  if ($creating) {
      $inlineErrors = ['name', 'email', 'position', 'department', 'password'];
  } elseif ($profileId !== '') {
      $inlineErrors = ['position', 'department'];
  }
@endphp

@section('content')
  <div class="page-head">
    <h1>Пользователи</h1>
  </div>
  @include('partials.errors', ['inlineErrors' => $inlineErrors])
  <div class="user-tools">
    <form class="field directory-search" method="GET" action="{{ route('admin.users.index') }}">
      <label for="user-search">Поиск</label>
      <input id="user-search" type="search" name="q" value="{{ $query }}" placeholder="ФИО" data-user-search autocomplete="off">
      <p id="user-search-status" class="visually-hidden" aria-live="polite"></p>
    </form>
    <button class="btn" type="button" data-new-teacher aria-controls="new-teacher" aria-expanded="{{ $creating ? 'true' : 'false' }}">Новый преподаватель</button>
  </div>
  <section class="panel" id="new-teacher" @unless ($creating) hidden @endunless>
    <h2>Новый преподаватель</h2>
    <p class="field-note">Преподаватели не регистрируются сами. Временный пароль передайте лично: письма нет.</p>
    <form method="POST" action="{{ route('admin.teachers.store') }}" novalidate>
      @csrf
      <input type="hidden" name="form" value="teacher">
      <div class="field">
        <label for="name">ФИО</label>
        <input id="name" type="text" name="name" value="{{ $creating ? old('name') : '' }}" @if ($creating && $errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
        @if ($creating)
          @error('name')
            <p class="error" id="name-error">{{ $message }}</p>
          @enderror
        @endif
      </div>
      <div class="field">
        <label for="email">Почта</label>
        <input id="email" type="email" name="email" value="{{ $creating ? old('email') : '' }}" inputmode="email" autocomplete="off" @if ($creating && $errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif>
        @if ($creating)
          @error('email')
            <p class="error" id="email-error">{{ $message }}</p>
          @enderror
        @endif
      </div>
      <div class="field">
        <label for="position">Должность</label>
        <input id="position" type="text" name="position" value="{{ $creating ? old('position') : '' }}" @if ($creating && $errors->has('position')) aria-invalid="true" aria-describedby="position-error" @endif>
        @if ($creating)
          @error('position')
            <p class="error" id="position-error">{{ $message }}</p>
          @enderror
        @endif
      </div>
      <div class="field">
        <label for="department">Кафедра</label>
        <input id="department" type="text" name="department" value="{{ $creating ? old('department') : '' }}" @if ($creating && $errors->has('department')) aria-invalid="true" aria-describedby="department-error" @endif>
        @if ($creating)
          @error('department')
            <p class="error" id="department-error">{{ $message }}</p>
          @enderror
        @endif
      </div>
      <div class="field">
        <label for="password">Временный пароль</label>
        <input id="password" type="text" name="password" value="{{ $creating ? old('password') : '' }}" autocomplete="off" @if ($creating && $errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
        <p class="field-note">Не короче 8 символов.</p>
        @if ($creating)
          @error('password')
            <p class="error" id="password-error">{{ $message }}</p>
          @enderror
        @endif
      </div>
      <p class="form-actions">
        <button class="btn" type="submit">Создать преподавателя</button>
        <button class="btn btn-ghost" type="button" data-new-teacher-cancel>Отмена</button>
      </p>
    </form>
  </section>
  <div class="table-wrap person-cards admin-users">
    <table class="data-table" data-user-directory>
      <thead>
        <tr>
          <th>ФИО</th>
          <th>Почта</th>
          <th>Роль</th>
          <th>Должность или группа</th>
          <th>Состояние</th>
          <th data-skip>Действие</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($users as $user)
          <tr class="person-card" id="user-{{ $user->id }}" data-search="{{ $user->search_text }}" @unless ($user->search_match) hidden @endunless>
            <td class="person-name">{{ $user->name }}</td>
            <td class="person-mail"><span class="cell-label">Почта</span>{{ $user->email }}</td>
            <td class="person-role"><span class="cell-label">Роль</span>{{ $user->role->name }}</td>
            <td class="person-fact">
              <span class="cell-label">Должность или группа</span>
              @if ($user->teacherProfile)
                {{ $user->teacherProfile->position }}, {{ $user->teacherProfile->department }}
              @else
                {{ $user->studentProfile->group_name ?? '—' }}
              @endif
            </td>
            <td class="person-state">
              @if ($user->is_active)
                <span class="state-on">активен</span>
              @else
                <span class="state-off">заблокирован</span>
              @endif
            </td>
            <td class="person-actions">
              @can('block', $user)
                <div class="actions">
                  @if ($user->is_active)
                    <a class="btn btn-small btn-ghost" href="{{ route('admin.users.block.confirm', $user) }}">Заблокировать</a>
                  @else
                    <a class="btn btn-small btn-ghost" href="{{ route('admin.users.unblock.confirm', $user) }}">Разблокировать</a>
                  @endif
                  @can('resetPassword', $user)
                    <a class="btn btn-small btn-ghost" href="{{ route('admin.users.password.confirm', $user) }}">Новый пароль</a>
                  @endcan
                </div>
                @can('updateProfile', $user)
                  @php $profileError = $profileId === (string) $user->id; @endphp
                  <details class="password-box profile-box" @if ($profileError) open @endif>
                    <summary>Должность и кафедра</summary>
                    <form method="POST" action="{{ route('admin.users.profile', $user) }}" novalidate>
                      @csrf
                      <input type="hidden" name="profile_user" value="{{ $user->id }}">
                      <label class="field">
                        Должность
                        <input type="text" name="position" value="{{ $profileError ? old('position') : $user->teacherProfile->position }}" @if ($profileError && $errors->has('position')) aria-invalid="true" aria-describedby="position-error-{{ $user->id }}" @endif>
                      </label>
                      @if ($profileError)
                        @error('position')
                          <p class="error" id="position-error-{{ $user->id }}">{{ $message }}</p>
                        @enderror
                      @endif
                      <label class="field">
                        Кафедра
                        <input type="text" name="department" value="{{ $profileError ? old('department') : $user->teacherProfile->department }}" @if ($profileError && $errors->has('department')) aria-invalid="true" aria-describedby="department-error-{{ $user->id }}" @endif>
                      </label>
                      @if ($profileError)
                        @error('department')
                          <p class="error" id="department-error-{{ $user->id }}">{{ $message }}</p>
                        @enderror
                      @endif
                      <button class="btn btn-small" type="submit">Сохранить</button>
                    </form>
                  </details>
                @endcan
              @endcan
            </td>
          </tr>
        @endforeach
        @if ($users->isNotEmpty())
          <tr data-static data-nomatch @if ($searchHasMatch) hidden @endif>
            <td colspan="6" class="muted">Нет совпадений</td>
          </tr>
        @endif
      </tbody>
    </table>
  </div>
  @if ($creating || $profileId !== '')
    <script>
      document.getElementById(@json($creating ? 'new-teacher' : 'user-'.$profileId))?.scrollIntoView();
    </script>
  @endif
@endsection
