@extends('layouts.app')

@section('title', 'Пользователи')

@php
  $creating = in_array(old('form'), ['user', 'teacher'], true);
  $kind = old('kind', 'teacher');
  if (! in_array($kind, ['teacher', 'student'], true)) {
      $kind = 'teacher';
  }
  $profileId = (string) old('profile_user');
  $inlineErrors = [];
  if ($creating) {
      $inlineErrors = $kind === 'student'
          ? ['name', 'email', 'group_name', 'password']
          : ['name', 'email', 'position', 'department', 'password'];
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
    <button class="btn" type="button" data-new-user aria-controls="new-user" aria-expanded="{{ $creating ? 'true' : 'false' }}" aria-label="Новый пользователь">Новый</button>
  </div>
  <section class="panel" id="new-user" @unless ($creating) hidden @endunless>
    <h2>Новый пользователь</h2>
    <p class="field-note">Преподаватели не регистрируются сами. Временный пароль передайте лично: письма нет.</p>
    <form method="POST" action="{{ route('admin.teachers.store') }}" novalidate>
      @csrf
      <input type="hidden" name="form" value="user">
      <fieldset class="field format">
        <legend>Кого создать</legend>
        <div class="choices">
          <label class="choice">
            <input type="radio" name="kind" value="teacher" @checked($kind === 'teacher')>
            Преподаватель
          </label>
          <label class="choice">
            <input type="radio" name="kind" value="student" @checked($kind === 'student')>
            Студент
          </label>
        </div>
      </fieldset>
      <div class="field">
        <label class="visually-hidden" for="name">ФИО</label>
        <input id="name" type="text" name="name" value="{{ $creating ? old('name') : '' }}" placeholder="ФИО" @if ($creating && $errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
        @if ($creating)
          @error('name')
            <p class="error" id="name-error">{{ $message }}</p>
          @enderror
        @endif
      </div>
      <div class="field">
        <label class="visually-hidden" for="email">Почта</label>
        <input id="email" type="email" name="email" value="{{ $creating ? old('email') : '' }}" placeholder="Почта" inputmode="email" autocomplete="off" @if ($creating && $errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif>
        @if ($creating)
          @error('email')
            <p class="error" id="email-error">{{ $message }}</p>
          @enderror
        @endif
      </div>
      <div class="field" data-kind="teacher" @unless ($kind === 'teacher') hidden @endunless>
        <label class="visually-hidden" for="position">Должность</label>
        <input id="position" type="text" name="position" value="{{ $creating && $kind === 'teacher' ? old('position') : '' }}" placeholder="Должность" @disabled($kind !== 'teacher') @if ($creating && $kind === 'teacher' && $errors->has('position')) aria-invalid="true" aria-describedby="position-error" @endif>
        @if ($creating && $kind === 'teacher')
          @error('position')
            <p class="error" id="position-error">{{ $message }}</p>
          @enderror
        @endif
      </div>
      <div class="field" data-kind="teacher" @unless ($kind === 'teacher') hidden @endunless>
        <label class="visually-hidden" for="department">Кафедра</label>
        <input id="department" type="text" name="department" value="{{ $creating && $kind === 'teacher' ? old('department') : '' }}" placeholder="Кафедра" @disabled($kind !== 'teacher') @if ($creating && $kind === 'teacher' && $errors->has('department')) aria-invalid="true" aria-describedby="department-error" @endif>
        @if ($creating && $kind === 'teacher')
          @error('department')
            <p class="error" id="department-error">{{ $message }}</p>
          @enderror
        @endif
      </div>
      <div class="field" data-kind="student" @unless ($kind === 'student') hidden @endunless>
        <label class="visually-hidden" for="group_name">Группа</label>
        <input id="group_name" type="text" name="group_name" value="{{ $creating && $kind === 'student' ? old('group_name') : '' }}" placeholder="Группа" @disabled($kind !== 'student') @if ($creating && $kind === 'student' && $errors->has('group_name')) aria-invalid="true" aria-describedby="group_name-error" @endif>
        @if ($creating && $kind === 'student')
          @error('group_name')
            <p class="error" id="group_name-error">{{ $message }}</p>
          @enderror
        @endif
      </div>
      <div class="field">
        <label class="visually-hidden" for="password">Временный пароль</label>
        <input id="password" type="text" name="password" value="{{ $creating ? old('password') : '' }}" placeholder="Временный пароль" autocomplete="off" @if ($creating && $errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
        <p class="field-note">Не короче 8 символов.</p>
        @if ($creating)
          @error('password')
            <p class="error" id="password-error">{{ $message }}</p>
          @enderror
        @endif
      </div>
      <p class="form-actions">
        <button class="btn" type="submit">Создать</button>
        <button class="btn btn-ghost" type="button" data-new-user-cancel>Отмена</button>
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
                      <div class="field">
                        <label class="visually-hidden" for="position-{{ $user->id }}">Должность</label>
                        <input id="position-{{ $user->id }}" type="text" name="position" value="{{ $profileError ? old('position') : $user->teacherProfile->position }}" placeholder="Должность" @if ($profileError && $errors->has('position')) aria-invalid="true" aria-describedby="position-error-{{ $user->id }}" @endif>
                      </div>
                      @if ($profileError)
                        @error('position')
                          <p class="error" id="position-error-{{ $user->id }}">{{ $message }}</p>
                        @enderror
                      @endif
                      <div class="field">
                        <label class="visually-hidden" for="department-{{ $user->id }}">Кафедра</label>
                        <input id="department-{{ $user->id }}" type="text" name="department" value="{{ $profileError ? old('department') : $user->teacherProfile->department }}" placeholder="Кафедра" @if ($profileError && $errors->has('department')) aria-invalid="true" aria-describedby="department-error-{{ $user->id }}" @endif>
                      </div>
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
      document.getElementById(@json($creating ? 'new-user' : 'user-'.$profileId))?.scrollIntoView();
    </script>
  @endif
@endsection
