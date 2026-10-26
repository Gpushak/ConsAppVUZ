@extends('layouts.app')

@section('title', 'Пользователи')

@section('content')
  <div class="page-head">
    <div>
      <h1>Пользователи</h1>
      <p class="lead">Учётные записи кафедры. Преподавателя создаёт администратор.</p>
    </div>
  </div>
  @include('partials.errors')
  <div class="table-wrap">
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
          <tr>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>{{ $user->role->name }}</td>
            <td>{{ $user->teacherProfile->position ?? $user->studentProfile->group_name ?? '—' }}</td>
            <td>
              @if ($user->is_active)
                <span class="state-on">активен</span>
              @else
                <span class="state-off">заблокирован</span>
              @endif
            </td>
            <td>
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
