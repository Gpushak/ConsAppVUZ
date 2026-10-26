@extends('layouts.app')

@section('title', 'Новый слот')

@php
  $format = old('format', 'in_person');
  $online = $format === 'online';

  $rawDate = old('date');
  $dateValue = '';
  if (is_string($rawDate) && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $rawDate, $parts)) {
      $dateValue = $parts[3].'-'.$parts[2].'-'.$parts[1];
  } elseif (is_string($rawDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
      $dateValue = $rawDate;
  }

  $timeValue = function (string $key): string {
      $value = old($key);
      if (! is_string($value) || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value) !== 1) {
          return '';
      }

      return substr($value, 0, 5);
  };
@endphp

@section('content')
  <div class="page-head">
    <h1>Новый слот</h1>
  </div>
  <div class="panel">
    @include('partials.errors')
    <form method="POST" action="{{ route('teacher.slots.store') }}" data-slot-form>
      @csrf
      <fieldset class="field format">
        <legend>Формат</legend>
        <label class="choice">
          <input id="format_in_person" type="radio" name="format" value="in_person" @checked(! $online)>
          Очно
        </label>
        <label class="choice">
          <input id="format_online" type="radio" name="format" value="online" @checked($online)>
          Онлайн
        </label>
      </fieldset>
      <div class="field">
        <label for="date">Дата</label>
        <input id="date" type="date" name="date" value="{{ $dateValue }}" placeholder="31.10.2026" required>
      </div>
      <div class="field">
        <label for="start_time">Начало</label>
        <input id="start_time" type="time" name="start_time" value="{{ $timeValue('start_time') }}" placeholder="16:00" step="300" required>
      </div>
      <div class="field">
        <label for="end_time">Конец</label>
        <input id="end_time" type="time" name="end_time" value="{{ $timeValue('end_time') }}" placeholder="16:45" step="300" required>
      </div>
      <div data-in-person @if ($online) hidden @endif>
        <div class="field">
          <label for="building">Корпус</label>
          <select id="building" name="building" size="8" @disabled($online) @required(! $online)>
            <option value="" @selected(old('building') === null || old('building') === '')>Выберите корпус</option>
            @foreach (\App\Support\Building::LIST as $building)
              <option value="{{ $building }}" @selected(old('building') === $building)>{{ $building }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="room">Аудитория</label>
          <input id="room" type="text" name="room" value="{{ old('room') }}" placeholder="220" @disabled($online) @required(! $online)>
        </div>
      </div>
      <div class="field">
        <label for="comment">Комментарий, необязательно</label>
        <textarea id="comment" name="comment" maxlength="500" placeholder="принести черновик курсовой">{{ old('comment') }}</textarea>
      </div>
      <div class="field">
        <label for="meeting_url">{{ $online ? 'Ссылка' : 'Ссылка, необязательно' }}</label>
        <input id="meeting_url" type="text" name="meeting_url" value="{{ old('meeting_url') }}" placeholder="https://" @required($online)>
      </div>
      <p class="form-actions"><button class="btn" type="submit">Опубликовать</button></p>
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
