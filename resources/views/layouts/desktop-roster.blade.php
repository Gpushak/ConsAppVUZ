<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Записавшиеся') — Запись на консультацию</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <link rel="stylesheet" href="{{ asset('css/desktop.css') }}">
</head>
<body class="desk desk-roster">
  <main class="desk-main">
    @yield('content')
  </main>
  <script src="{{ asset('js/tables.js') }}"></script>
</body>
</html>
