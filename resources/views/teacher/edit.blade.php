@extends('layouts.app')

@section('title', 'Изменить слот')

@php
  $locked = $slot->timeIsLocked();
  $format = old('format', $slot->isOnline() ? 'online' : 'in_person');
  $online = $format === 'online';

  $dateInput = function (mixed $raw): string {
      if (! is_string($raw) || $raw === '') {
          return '';
      }
      if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $raw, $parts)) {
          return $parts[3].'-'.$parts[2].'-'.$parts[1];
      }
      if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
          return $raw;
      }

      return '';
  };
  $dateValue = $locked
      ? $slot->starts_at->format('Y-m-d')
      : $dateInput(old('date', $slot->starts_at->format('Y-m-d')));
  $startValue = $locked ? $slot->starts_at->format('H:i') : (function () use ($slot) {
      $value = old('start_time', $slot->starts_at->format('H:i'));

      return is_string($value) ? substr($value, 0, 5) : $slot->starts_at->format('H:i');
  })();
  $endValue = $locked ? $slot->ends_at->format('H:i') : (function () use ($slot) {
      $value = old('end_time', $slot->ends_at->format('H:i'));

      return is_string($value) ? substr($value, 0, 5) : $slot->ends_at->format('H:i');
  })();
  $buildingValue = old('building', $slot->building);
  $roomValue = old('room', $slot->room);
@endphp

@section('content')
  <div class="page-head">
    <h1>Изменить слот</h1>
  </div>
  <div class="panel slot-panel">
    @include('partials.errors')
    <p class="meta">{{ $slot->dateLabel() }}, {{ $slot->timeRange() }}</p>
    <form class="slot-form" method="POST" action="{{ route('teacher.slots.update', $slot) }}" data-slot-form>
      @csrf
      <div class="slot-fields">
        <fieldset class="field format">
          <legend>Формат</legend>
          <div class="choices">
            <label class="choice">
              <input id="format_in_person" type="radio" name="format" value="in_person" @checked(! $online)>
              Очно
            </label>
            <label class="choice">
              <input id="format_online" type="radio" name="format" value="online" @checked($online)>
              Онлайн
            </label>
          </div>
        </fieldset>
        <div class="field">
          <label for="date">Дата</label>
          @if ($locked)
            <input type="hidden" name="date" value="{{ $dateValue }}">
            <input id="date" type="date" value="{{ $dateValue }}" disabled>
          @else
            <input id="date" type="date" name="date" value="{{ $dateValue }}" required>
          @endif
        </div>
        <div class="field">
          <label for="start_time">Начало</label>
          @if ($locked)
            <input type="hidden" name="start_time" value="{{ $startValue }}">
            <input id="start_time" type="time" value="{{ $startValue }}" step="300" disabled>
          @else
            <input id="start_time" type="time" name="start_time" value="{{ $startValue }}" step="300" required>
          @endif
        </div>
        <div class="field">
          <label for="end_time">Конец</label>
          @if ($locked)
            <input type="hidden" name="end_time" value="{{ $endValue }}">
            <input id="end_time" type="time" value="{{ $endValue }}" step="300" disabled>
          @else
            <input id="end_time" type="time" name="end_time" value="{{ $endValue }}" step="300" required>
          @endif
        </div>
      </div>
      @if ($locked)
        <p class="field-note">Время нельзя изменить: на слот уже есть запись.</p>
      @endif
      <div class="slot-place" data-in-person @if ($online) hidden @endif>
        <div class="field">
          <label for="building">Корпус</label>
          <select id="building" name="building" @disabled($online) @required(! $online)>
            <option value="" @selected($buildingValue === null || $buildingValue === '')>Выберите корпус</option>
            @foreach (\App\Support\Building::LIST as $building)
              <option value="{{ $building }}" @selected($buildingValue === $building)>{{ $building }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="room">Аудитория</label>
          <input id="room" type="text" name="room" value="{{ $roomValue }}" placeholder="220" @disabled($online) @required(! $online)>
        </div>
      </div>
      <div class="field">
        <label for="comment">Комментарий, необязательно</label>
        <textarea id="comment" name="comment" maxlength="500" placeholder="принести черновик курсовой">{{ old('comment', $slot->comment) }}</textarea>
      </div>
      <div class="field">
        <label for="meeting_url">{{ $online ? 'Ссылка' : 'Ссылка, необязательно' }}</label>
        <input id="meeting_url" type="text" name="meeting_url" value="{{ old('meeting_url', $slot->meeting_url) }}" placeholder="https://" @required($online)>
      </div>
      <p class="form-actions"><button class="btn" type="submit">Сохранить</button></p>
    </form>
  </div>
  <script>
    (function () {
      var form = document.querySelector('[data-slot-form]');
      if (!form) {
        return;
      }
      var inPerson = form.querySelector('[data-in-person]');
      var building = form.querySelector('#building');
      var room = form.querySelector('#room');
      var url = form.querySelector('#meeting_url');
      var urlLabel = form.querySelector('label[for="meeting_url"]');

      function sync() {
        var online = form.querySelector('input[name="format"]:checked').value === 'online';
        inPerson.hidden = online;
        building.disabled = online;
        room.disabled = online;
        building.required = !online;
        room.required = !online;
        url.required = online;
        urlLabel.textContent = online ? 'Ссылка' : 'Ссылка, необязательно';
      }

      form.querySelectorAll('input[name="format"]').forEach(function (input) {
        input.addEventListener('change', sync);
      });
      sync();
    })();
  </script>
@endsection
