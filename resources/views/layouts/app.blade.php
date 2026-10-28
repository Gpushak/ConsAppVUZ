<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Запись на консультацию') — Запись на консультацию</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
  <header class="top">
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
      @unless (auth()->user()->must_change_password)
        <nav>
          @if (auth()->user()->isStudent())
            <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>Слоты избранных</a>
            <a href="{{ route('teachers.index') }}" @if (request()->routeIs('teachers.*')) aria-current="page" @endif>Преподаватели</a>
            <a href="{{ route('bookings.index') }}" @if (request()->routeIs('bookings.*')) aria-current="page" @endif>Мои записи</a>
          @elseif (auth()->user()->isTeacher())
            <a href="{{ route('teacher.slots.index') }}" @if (request()->routeIs('teacher.slots.index')) aria-current="page" @endif>Мои слоты</a>
            <a href="{{ route('teacher.slots.create') }}" @if (request()->routeIs('teacher.slots.create')) aria-current="page" @endif>Новый слот</a>
          @elseif (auth()->user()->isAdmin())
            <a href="{{ route('admin.users.index') }}" @if (request()->routeIs('admin.*')) aria-current="page" @endif>Пользователи</a>
          @endif
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="link-button logout" type="submit">Выйти</button>
          </form>
        </nav>
      @endunless
    @endauth
  </header>
  <main>
    @yield('content')
  </main>
  <script src="{{ asset('js/tables.js') }}"></script>
</body>
</html>
