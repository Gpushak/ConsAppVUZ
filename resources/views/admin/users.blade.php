@extends('layouts.app')

@section('title', 'Пользователи')

@section('content')
  <div class="page-head">
    <h1>Пользователи</h1>
  </div>
  @include('partials.errors')
  <div class="table-wrap person-cards">
    <table class="data-table">
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
          <tr class="person-card">
            <td class="person-name">{{ $user->name }}</td>
            <td class="person-extra">{{ $user->email }}</td>
            <td class="person-extra">{{ $user->role->name }}</td>
            <td class="person-fact">
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
                    <form method="POST" action="{{ route('admin.users.unblock', $user) }}">
                      @csrf
                      <button class="btn btn-small btn-ghost" type="submit">Разблокировать</button>
                    </form>
                  @endif
                </div>
                @can('resetPassword', $user)
                  <details class="password-box">
                    <summary>Новый пароль</summary>
                    <form method="POST" action="{{ route('admin.users.password', $user) }}">
                      @csrf
                      <input type="text" name="password" minlength="8" required autocomplete="off" placeholder="временный пароль">
                      <button class="btn btn-small" type="submit">Задать</button>
                    </form>
                  </details>
                @endcan
                @can('updateProfile', $user)
                  <details class="password-box profile-box" @if ((string) old('profile_user') === (string) $user->id) open @endif>
                    <summary>Должность и кафедра</summary>
                    <form method="POST" action="{{ route('admin.users.profile', $user) }}">
                      @csrf
                      <input type="hidden" name="profile_user" value="{{ $user->id }}">
                      <label>
                        Должность
                        <input type="text" name="position" value="{{ (string) old('profile_user') === (string) $user->id ? old('position') : $user->teacherProfile->position }}" required>
                      </label>
                      <label>
                        Кафедра
                        <input type="text" name="department" value="{{ (string) old('profile_user') === (string) $user->id ? old('department') : $user->teacherProfile->department }}" required>
                      </label>
                      <button class="btn btn-small" type="submit">Сохранить</button>
                    </form>
                  </details>
                @endcan
              @endcan
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <section class="panel">
    <h2>Новый преподаватель</h2>
    <form method="POST" action="{{ route('admin.teachers.store') }}">
      @csrf
      <div class="field">
        <label for="name">ФИО</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required>
      </div>
      <div class="field">
        <label for="email">Почта</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required>
      </div>
      <div class="field">
        <label for="position">Должность</label>
        <input id="position" type="text" name="position" value="{{ old('position') }}" required>
      </div>
      <div class="field">
        <label for="department">Кафедра</label>
        <input id="department" type="text" name="department" value="{{ old('department') }}" required>
      </div>
      <div class="field">
        <label for="password">Временный пароль</label>
        <input id="password" type="text" name="password" value="{{ old('password') }}" minlength="8" required autocomplete="off">
      </div>
      <p class="form-actions"><button class="btn" type="submit">Создать преподавателя</button></p>
    </form>
  </section>
@endsection
