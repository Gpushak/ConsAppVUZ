<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Запись на консультацию') — Запись на консультацию</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
  <a class="skip-link" href="#content">К содержанию</a>
  <header class="top">
    <div class="head-row">
      <a class="brand" href="{{ auth()->check() ? route('home') : route('login') }}">
        <img src="{{ asset('images/pnipu-kons.png') }}" alt="Пермский политех, запись на консультацию">
      </a>
      @auth
        @unless (auth()->user()->must_change_password)
          <nav>
            @if (auth()->user()->isStudent())
              <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>Слоты избранных</a>
              <a href="{{ route('teachers.index') }}" @if (request()->routeIs('teachers.*')) aria-current="page" @endif>Преподаватели</a>
              <a href="{{ route('bookings.index') }}" @if (request()->routeIs('bookings.*')) aria-current="page" @endif>Мои записи</a>
            @elseif (auth()->user()->isTeacher())
              <a href="{{ route('teacher.slots.index') }}" @if (request()->routeIs('teacher.slots.index')) aria-current="page" @endif>Мои слоты</a>
              <a href="{{ route('teacher.slots.create') }}" @if (request()->routeIs('teacher.slots.create')) aria-current="page" @endif>Новая консультация</a>
            @elseif (auth()->user()->isAdmin())
              <a href="{{ route('admin.users.index') }}" @if (request()->routeIs('admin.*')) aria-current="page" @endif>Пользователи</a>
            @endif
          </nav>
        @endunless
        <div class="who">
          <strong>{{ auth()->user()->name }}</strong>
          <span>{{ auth()->user()->roleLine() }}</span>
          @unless (auth()->user()->must_change_password)
            <a href="{{ route('password.edit') }}">Сменить пароль</a>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button class="link-button logout" type="submit">Выйти</button>
            </form>
          @endunless
        </div>
      @endauth
    </div>
  </header>
  <main id="content" tabindex="-1">
    @yield('content')
  </main>
  <footer class="site-footer">
    <div class="site-footer-bar">
      <p class="site-footer-name">
        Запись на консультацию
        <span>Кафедра информационных технологий и автоматизированных систем</span>
      </p>
      <nav aria-label="Сайт университета">
        <a href="https://pstu.ru/">ПНИПУ</a>
        <a href="https://pstu.ru/about-the-university/structure-and-governing-bodies/faculties/elektrotekhnicheskiy/">Электротехнический факультет</a>
        <a href="https://pstu.ru/about-the-university/structure-and-governing-bodies/faculties/departments/informatsionnye-tekhnologii-i-avtomatizirovannye-sistemy/o-kafedre/">Кафедра ИТАС</a>
      </nav>
    </div>
  </footer>
  <button class="to-top" type="button" hidden>Наверх</button>
  <script>
    (function () {
      var button = document.querySelector('.to-top');
      var content = document.getElementById('content');
      if (!button || !content) {
        return;
      }
      function sync() {
        button.hidden = window.scrollY === 0;
      }
      sync();
      window.addEventListener('scroll', sync, { passive: true });
      button.addEventListener('click', function () {
        window.scrollTo(0, 0);
        button.hidden = true;
        content.focus();
      });
    })();
  </script>
  <script src="{{ asset('js/tables.js') }}"></script>
</body>
</html>
