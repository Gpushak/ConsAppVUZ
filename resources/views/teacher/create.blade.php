@extends('layouts.app')

@section('title', 'Новый слот')

@php
  $format = old('format', 'in_person');
  $online = $format === 'online';

  $dateInput = function (mixed $raw): string {
      if (! is_string($raw)) {
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
  $dateValue = $dateInput(old('date'));
  $repeat = filter_var(old('repeat_weekly'), FILTER_VALIDATE_BOOLEAN);
  $repeatUntilValue = $dateInput(old('repeat_until'));

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
  <div class="panel slot-panel">
    @include('partials.errors', ['inlineErrors' => ['date', 'start_time', 'end_time', 'format', 'building', 'room', 'comment', 'meeting_url', 'repeat_until']])
    <form class="slot-form" method="POST" action="{{ route('teacher.slots.store') }}" data-slot-form>
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
          <p class="field-note" id="format-note">Очно: корпус из списка и аудитория. Онлайн: ссылка http:// или https://, без корпуса и аудитории.</p>
          @error('format')
            <p class="error" id="format-error">{{ $message }}</p>
          @enderror
        </fieldset>
        <div class="field">
          <label for="date">Дата</label>
          <input id="date" type="date" name="date" value="{{ $dateValue }}" required @if ($errors->has('date')) aria-invalid="true" aria-describedby="date-error" @endif>
          @error('date')
            <p class="error" id="date-error">{{ $message }}</p>
          @enderror
        </div>
        <div class="field">
          <label for="start_time">Начало</label>
          <input id="start_time" type="time" name="start_time" value="{{ $timeValue('start_time') }}" placeholder="16:00" step="300" required @if ($errors->has('start_time')) aria-invalid="true" aria-describedby="start_time-error" @endif>
          @error('start_time')
            <p class="error" id="start_time-error">{{ $message }}</p>
          @enderror
        </div>
        <div class="field">
          <label for="end_time">Конец</label>
          <input id="end_time" type="time" name="end_time" value="{{ $timeValue('end_time') }}" placeholder="16:45" step="300" required @if ($errors->has('end_time')) aria-invalid="true" aria-describedby="end_time-error" @endif>
          @error('end_time')
            <p class="error" id="end_time-error">{{ $message }}</p>
          @enderror
        </div>
      </div>
      <div class="repeat-row">
        <label class="choice" for="repeat_weekly">
          <input id="repeat_weekly" type="checkbox" name="repeat_weekly" value="1" @checked($repeat)>
          Повторять каждую неделю
        </label>
        <div class="field">
          <label for="repeat_until">Последний день повтора</label>
          <input id="repeat_until" type="date" name="repeat_until" value="{{ $repeatUntilValue }}" max="{{ \App\Support\Term::WORK_ENDS_ON }}" @required($repeat) @if ($errors->has('repeat_until')) aria-invalid="true" aria-describedby="repeat_until-error" @endif>
          @error('repeat_until')
            <p class="error" id="repeat_until-error">{{ $message }}</p>
          @enderror
          <p class="field-note" id="repeat-until-note" data-limit="{{ \App\Support\Term::workEndsOn()->format('d.m.Y') }}">{{ $repeat ? 'Последний день серии, не позже '.\App\Support\Term::workEndsOn()->format('d.m.Y').'.' : 'Учитывается, только если включено «Повторять каждую неделю». Не позже '.\App\Support\Term::workEndsOn()->format('d.m.Y').'.' }}</p>
        </div>
      </div>
      <div class="slot-place" data-in-person @if ($online) hidden @endif>
        <div class="field">
          <label for="building">Корпус</label>
          <select id="building" name="building" @disabled($online) @required(! $online) @if ($errors->has('building')) aria-invalid="true" aria-describedby="building-error" @endif>
            <option value="" @selected(old('building') === null || old('building') === '')>Выберите корпус</option>
            @foreach (\App\Support\Building::LIST as $building)
              <option value="{{ $building }}" @selected(old('building') === $building)>{{ $building }}</option>
            @endforeach
          </select>
          @error('building')
            <p class="error" id="building-error">{{ $message }}</p>
          @enderror
        </div>
        <div class="field">
          <label for="room">Аудитория</label>
          <input id="room" type="text" name="room" value="{{ old('room') }}" placeholder="220" maxlength="32" @disabled($online) @required(! $online) @if ($errors->has('room')) aria-invalid="true" aria-describedby="room-error" @endif>
          @error('room')
            <p class="error" id="room-error">{{ $message }}</p>
          @enderror
        </div>
      </div>
      <div class="field">
        <label for="comment">Комментарий, необязательно</label>
        <textarea id="comment" name="comment" maxlength="500" placeholder="принести черновик курсовой" @if ($errors->has('comment')) aria-invalid="true" aria-describedby="comment-error" @endif>{{ old('comment') }}</textarea>
        @error('comment')
          <p class="error" id="comment-error">{{ $message }}</p>
        @enderror
      </div>
      <div class="field">
        <label for="meeting_url">{{ $online ? 'Ссылка на встречу' : 'Ссылка на встречу, необязательно' }}</label>
        <input id="meeting_url" type="text" name="meeting_url" value="{{ old('meeting_url') }}" placeholder="https://" maxlength="500" @required($online) @if ($errors->has('meeting_url')) aria-invalid="true" aria-describedby="meeting_url-error" @endif>
        @error('meeting_url')
          <p class="error" id="meeting_url-error">{{ $message }}</p>
        @enderror
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
      var repeat = form.querySelector('#repeat_weekly');
      var until = form.querySelector('#repeat_until');
      var untilNote = form.querySelector('#repeat-until-note');

      function sync() {
        var online = form.querySelector('input[name="format"]:checked').value === 'online';
        inPerson.hidden = online;
        building.disabled = online;
        room.disabled = online;
        building.required = !online;
        room.required = !online;
        url.required = online;
        urlLabel.textContent = online ? 'Ссылка на встречу' : 'Ссылка на встречу, необязательно';
        until.required = repeat.checked;
        var limit = untilNote.getAttribute('data-limit');
        untilNote.textContent = repeat.checked
          ? 'Последний день серии, не позже ' + limit + '.'
          : 'Учитывается, только если включено «Повторять каждую неделю». Не позже ' + limit + '.';
      }

      function missing(input, message) {
        input.addEventListener('invalid', function () {
          input.setCustomValidity(input.validity.valueMissing ? message : '');
        });
        input.addEventListener('input', function () {
          input.setCustomValidity('');
        });
        input.addEventListener('change', function () {
          input.setCustomValidity('');
        });
      }

      missing(form.querySelector('#date'), 'Укажите дату.');
      missing(form.querySelector('#start_time'), 'Укажите начало.');
      missing(form.querySelector('#end_time'), 'Укажите конец.');
      missing(building, 'Выберите корпус из списка.');
      missing(room, 'Укажите аудиторию.');
      missing(url, 'Для онлайн-слота укажите ссылку http:// или https://.');
      missing(until, 'Укажите дату окончания повтора.');

      form.querySelectorAll('input[name="format"]').forEach(function (input) {
        input.addEventListener('change', sync);
      });
      repeat.addEventListener('change', sync);
      sync();
    })();
  </script>
@endsection
