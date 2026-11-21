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
  $laterDays = $slot->series_id === null ? 0 : $slot->laterSeriesDays()->count();
  $applySeries = (bool) old('apply_to_series');
  $describe = function (string $field) use ($errors, $locked): string {
      $ids = [];
      if ($errors->has($field)) {
          $ids[] = $field.'-error';
      }
      if ($locked) {
          $ids[] = 'start_time-lock';
      }

      return implode(' ', $ids);
  };
@endphp

@section('content')
  <div class="page-head">
    <h1>Изменить слот</h1>
  </div>
  <div class="panel slot-panel">
    @include('partials.errors', ['inlineErrors' => ['date', 'start_time', 'end_time', 'format', 'building', 'room', 'comment', 'meeting_url']])
    <p class="meta">{{ $slot->dateLabel() }}, {{ $slot->timeRange() }}</p>
    <form class="slot-form" method="POST" action="{{ route('teacher.slots.update', $slot) }}" data-slot-form>
      @csrf
      <div class="slot-fields">
        <fieldset class="field format" @if ($errors->has('format')) aria-describedby="format-error" @endif>
          <legend>Формат</legend>
          <div class="choices">
            <label class="choice">
              <input id="format_in_person" type="radio" name="format" value="in_person" @checked(! $online) @if ($errors->has('format')) aria-invalid="true" @endif>
              Очно
            </label>
            <label class="choice">
              <input id="format_online" type="radio" name="format" value="online" @checked($online) @if ($errors->has('format')) aria-invalid="true" @endif>
              Онлайн
            </label>
          </div>
          <p class="field-note" id="format-note">Очно: корпус из списка и аудитория. Онлайн: ссылка http:// или https://, без корпуса и аудитории.</p>
          @error('format')
            <p class="error" id="format-error">{{ $message }}</p>
          @enderror
        </fieldset>
        @php
          $dateDescribed = $describe('date');
          $startDescribed = $describe('start_time');
          $endDescribed = $describe('end_time');
        @endphp
        <div class="field">
          <label for="date">Дата</label>
          @if ($locked)
            <input type="hidden" name="date" value="{{ $dateValue }}">
            <input id="date" type="date" value="{{ $dateValue }}" disabled @if ($errors->has('date')) aria-invalid="true" @endif @if ($dateDescribed !== '') aria-describedby="{{ $dateDescribed }}" @endif>
          @else
            <input id="date" type="date" name="date" value="{{ $dateValue }}" required @if ($errors->has('date')) aria-invalid="true" @endif @if ($dateDescribed !== '') aria-describedby="{{ $dateDescribed }}" @endif>
          @endif
          @error('date')
            <p class="error" id="date-error">{{ $message }}</p>
          @enderror
        </div>
        <div class="field">
          <label for="start_time">Начало</label>
          @if ($locked)
            <input type="hidden" name="start_time" value="{{ $startValue }}">
            <input id="start_time" type="time" value="{{ $startValue }}" step="300" disabled @if ($errors->has('start_time')) aria-invalid="true" @endif @if ($startDescribed !== '') aria-describedby="{{ $startDescribed }}" @endif>
          @else
            <input id="start_time" type="time" name="start_time" value="{{ $startValue }}" step="300" required @if ($errors->has('start_time')) aria-invalid="true" @endif @if ($startDescribed !== '') aria-describedby="{{ $startDescribed }}" @endif>
          @endif
          @error('start_time')
            <p class="error" id="start_time-error">{{ $message }}</p>
          @enderror
          @if ($locked)
            <p class="field-note" id="start_time-lock">Время нельзя изменить: на слот уже есть запись.</p>
          @endif
        </div>
        <div class="field">
          <label for="end_time">Конец</label>
          @if ($locked)
            <input type="hidden" name="end_time" value="{{ $endValue }}">
            <input id="end_time" type="time" value="{{ $endValue }}" step="300" disabled @if ($errors->has('end_time')) aria-invalid="true" @endif @if ($endDescribed !== '') aria-describedby="{{ $endDescribed }}" @endif>
          @else
            <input id="end_time" type="time" name="end_time" value="{{ $endValue }}" step="300" required @if ($errors->has('end_time')) aria-invalid="true" @endif @if ($endDescribed !== '') aria-describedby="{{ $endDescribed }}" @endif>
          @endif
          @error('end_time')
            <p class="error" id="end_time-error">{{ $message }}</p>
          @enderror
        </div>
      </div>
      <div class="slot-place" data-in-person @if ($online) hidden @endif>
        <div class="field">
          <label for="building">Корпус</label>
          <select id="building" name="building" @disabled($online) @required(! $online) @if ($errors->has('building')) aria-invalid="true" aria-describedby="building-error" @endif>
            <option value="" @selected($buildingValue === null || $buildingValue === '')>Выберите корпус</option>
            @foreach (\App\Support\Building::LIST as $building)
              <option value="{{ $building }}" @selected($buildingValue === $building)>{{ $building }}</option>
            @endforeach
          </select>
          @error('building')
            <p class="error" id="building-error">{{ $message }}</p>
          @enderror
        </div>
        <div class="field">
          <label for="room">Аудитория</label>
          <input id="room" type="text" name="room" value="{{ $roomValue }}" placeholder="220" maxlength="32" @disabled($online) @required(! $online) @if ($errors->has('room')) aria-invalid="true" aria-describedby="room-error" @endif>
          @error('room')
            <p class="error" id="room-error">{{ $message }}</p>
          @enderror
        </div>
      </div>
      <div class="field">
        <label for="comment">Комментарий, необязательно</label>
        <textarea id="comment" name="comment" maxlength="500" placeholder="принести черновик курсовой" @if ($errors->has('comment')) aria-invalid="true" aria-describedby="comment-error" @endif>{{ old('comment', $slot->comment) }}</textarea>
        @error('comment')
          <p class="error" id="comment-error">{{ $message }}</p>
        @enderror
      </div>
      <div class="field">
        <label for="meeting_url">{{ $online ? 'Ссылка на встречу' : 'Ссылка на встречу, необязательно' }}</label>
        <input id="meeting_url" type="text" name="meeting_url" value="{{ old('meeting_url', $slot->meeting_url) }}" placeholder="https://" maxlength="500" @required($online) @if ($errors->has('meeting_url')) aria-invalid="true" aria-describedby="meeting_url-error" @endif>
        @error('meeting_url')
          <p class="error" id="meeting_url-error">{{ $message }}</p>
        @enderror
      </div>
      @if ($slot->series_id !== null)
        <div class="series-apply">
          <label class="choice" for="apply_to_series">
            <input id="apply_to_series" type="checkbox" name="apply_to_series" value="1" @checked($applySeries)>
            Применить к оставшимся дням серии
          </label>
          <p class="field-note" id="series-apply-note" data-off="{{ \App\Support\Ru::seriesApplyHint($laterDays) }}" data-on="{{ \App\Support\Ru::seriesDaysWillChange($laterDays) }}">{{ $applySeries ? \App\Support\Ru::seriesDaysWillChange($laterDays) : \App\Support\Ru::seriesApplyHint($laterDays) }}</p>
        </div>
      @endif
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
        urlLabel.textContent = online ? 'Ссылка на встречу' : 'Ссылка на встречу, необязательно';
      }

      function missing(input, message) {
        if (!input) {
          return;
        }
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

      var series = form.querySelector('#apply_to_series');
      var seriesNote = form.querySelector('#series-apply-note');
      if (series && seriesNote) {
        series.addEventListener('change', function () {
          seriesNote.textContent = series.checked ? seriesNote.getAttribute('data-on') : seriesNote.getAttribute('data-off');
        });
      }

      form.querySelectorAll('input[name="format"]').forEach(function (input) {
        input.addEventListener('change', sync);
      });
      sync();
    })();
  </script>
@endsection
