@php
  $desktopDialog = $desktopDialog ?? false;
  $fragment = request()->headers->get(\App\Support\DesktopWindow::FRAGMENT) === '1';
@endphp
@if ($fragment)
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>@yield('title', 'Запись на консультацию')</title>
</head>
<body>
  <div data-desktop-fragment @if ($desktopDialog) data-desktop-dialog @endif>
    @yield('content')
  </div>
</body>
</html>
@else
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Запись на консультацию') — Запись на консультацию</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <link rel="stylesheet" href="{{ asset('css/desktop.css') }}">
</head>
<body @class(['desk']) @auth data-role="{{ auth()->user()->role->slug }}" @endauth>
  <div class="desk-frame">
    <header class="desk-top">
      <a class="brand" href="{{ auth()->check() ? route('home') : route('login') }}">
        <img src="{{ asset('images/pnipu-kons.png') }}" alt="Запись на консультацию">
      </a>
      @auth
        <div class="who">
          <strong>{{ auth()->user()->name }}</strong>
          <span>{{ auth()->user()->roleLine() }}</span>
          @unless (auth()->user()->must_change_password)
            <a href="{{ route('password.edit') }}">Сменить пароль</a>
          @endunless
        </div>
      @endauth
    </header>
    <div class="desk-body">
      <nav class="desk-side" aria-label="Разделы">
        <div class="desk-nav">
          @guest
            <a href="{{ route('login') }}" @if (request()->routeIs('login', 'login.store')) aria-current="page" @endif>Вход</a>
            <a href="{{ route('register') }}" @if (request()->routeIs('register', 'register.store')) aria-current="page" @endif>Регистрация студента</a>
          @endguest
          @auth
            @unless (auth()->user()->must_change_password)
              @if (auth()->user()->isStudent())
                <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>Слоты избранных</a>
                <a href="{{ route('teachers.index') }}" @if (request()->routeIs('teachers.*')) aria-current="page" @endif>Преподаватели</a>
                <a href="{{ route('bookings.index') }}" @if (request()->routeIs('bookings.*') && ! request()->routeIs('bookings.cancel')) aria-current="page" @endif>Мои записи</a>
              @elseif (auth()->user()->isTeacher())
                <a href="{{ route('teacher.slots.index') }}" @if (request()->routeIs('teacher.slots.index')) aria-current="page" @endif>Мои слоты</a>
                <a href="{{ route('teacher.slots.create') }}" @if (request()->routeIs('teacher.slots.create')) aria-current="page" @endif>Новая консультация</a>
              @elseif (auth()->user()->isAdmin())
                <a href="{{ route('admin.users.index') }}" @if (request()->routeIs('admin.*')) aria-current="page" @endif>Пользователи</a>
              @endif
            @endunless
          @endauth
        </div>
        @auth
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-ghost desk-logout" type="submit">Выйти</button>
          </form>
        @endauth
      </nav>
      <div class="desk-stage">
        <dialog id="desk-dialog" class="desk-modal" @if ($desktopDialog) data-desktop-dialog="page" data-desktop-inline @endif>
          <div class="desk-modal-bar">
            <button type="button" class="btn btn-ghost btn-small" data-desk-close>Закрыть</button>
          </div>
          <div class="desk-modal-body">
            @if ($desktopDialog)
              @yield('content')
            @endif
          </div>
        </dialog>
        <main class="desk-main">
          @unless ($desktopDialog)
            @yield('content')
          @endunless
        </main>
      </div>
    </div>
  </div>
  <script src="{{ asset('js/tables.js') }}"></script>
  <script src="{{ asset('js/desktop.js') }}"></script>
</body>
</html>
@endif
