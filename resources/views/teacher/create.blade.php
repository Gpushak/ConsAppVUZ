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
  $repeatWeeks = match (old('repeat')) {
      '1', 1 => 1,
      '2', 2 => 2,
      'none' => 0,
      default => filter_var(old('repeat_weekly'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
  };
  $repeatUntilValue = $dateInput(old('repeat_until'));
  $repeatLimit = \App\Support\Term::workEndsOn()->format('d.m.Y');
  $repeatOnNote = 'Последний день серии, не позже '.$repeatLimit.'.';
  $repeatOffNote = 'Учитывается, только если выбран повтор: «Каждую неделю» или «Каждые 2 недели». Не позже '.$repeatLimit.'.';

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
    @include('partials.errors', ['inlineErrors' => ['date', 'start_time', 'end_time', 'format', 'building', 'room', 'comment', 'meeting_url', 'repeat', 'repeat_until']])
    <form class="slot-form" method="POST" action="{{ route('teacher.slots.store') }}" data-slot-form data-room-warning-url="{{ route('teacher.slots.room-warning', absolute: false) }}">
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
        <fieldset class="field format">
          <legend>Повтор</legend>
          <div class="choices">
            <label class="choice" for="repeat_none">
              <input id="repeat_none" type="radio" name="repeat" value="none" @checked($repeatWeeks === 0)>
              Не повторять
            </label>
            <label class="choice" for="repeat_week">
              <input id="repeat_week" type="radio" name="repeat" value="1" @checked($repeatWeeks === 1)>
              Каждую неделю
            </label>
            <label class="choice" for="repeat_biweek">
              <input id="repeat_biweek" type="radio" name="repeat" value="2" @checked($repeatWeeks === 2)>
              Каждые 2 недели
            </label>
          </div>
          @error('repeat')
            <p class="error" id="repeat-error">{{ $message }}</p>
          @enderror
        </fieldset>
        <div class="field">
          <label for="repeat_until">Последний день повтора</label>
          <input id="repeat_until" type="date" name="repeat_until" value="{{ $repeatUntilValue }}" max="{{ \App\Support\Term::WORK_ENDS_ON }}" @required($repeatWeeks > 0) @if ($errors->has('repeat_until')) aria-invalid="true" aria-describedby="repeat_until-error" @endif>
          @error('repeat_until')
            <p class="error" id="repeat_until-error">{{ $message }}</p>
          @enderror
          <p class="field-note" id="repeat-until-note" data-limit="{{ $repeatLimit }}" data-on="{{ $repeatOnNote }}" data-off="{{ $repeatOffNote }}">{{ $repeatWeeks > 0 ? $repeatOnNote : $repeatOffNote }}</p>
          <p class="error" id="repeat-until-cleared" hidden></p>
          <div id="repeat-until-cal" class="end-cal" hidden>
            <div class="cal-nav">
              <button type="button" class="cal-arrow" data-end-prev aria-label="Предыдущий месяц">‹</button>
              <span class="cal-title" data-end-title></span>
              <button type="button" class="cal-arrow" data-end-next aria-label="Следующий месяц">›</button>
            </div>
            <table class="month-cal end-month">
              <thead>
                <tr>
                  @foreach (['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'] as $dow)
                    <th scope="col">{{ $dow }}</th>
                  @endforeach
                </tr>
              </thead>
              <tbody data-end-days></tbody>
            </table>
          </div>
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
      @include('partials.room-warning')
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
      var dateInput = form.querySelector('#date');
      var until = form.querySelector('#repeat_until');
      var untilNote = form.querySelector('#repeat-until-note');
      var cleared = form.querySelector('#repeat-until-cleared');
      var cal = form.querySelector('#repeat-until-cal');
      var endView = null;
      var weekdays = ['понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота', 'воскресенье'];
      var months = ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];

      function repeatStep() {
        var chosen = form.querySelector('input[name="repeat"]:checked');
        if (!chosen || chosen.value === 'none') {
          return 0;
        }

        return chosen.value === '2' ? 14 : 7;
      }

      function parseIso(value) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        if (!match) {
          return null;
        }
        var year = Number(match[1]);
        var month = Number(match[2]);
        var day = Number(match[3]);
        var date = new Date(year, month - 1, day);
        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
          return null;
        }

        return date;
      }

      function iso(date) {
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');

        return date.getFullYear() + '-' + month + '-' + day;
      }

      function formatRu(date) {
        var day = String(date.getDate()).padStart(2, '0');
        var month = String(date.getMonth() + 1).padStart(2, '0');

        return day + '.' + month + '.' + date.getFullYear();
      }

      function dayDiff(from, to) {
        var start = Date.UTC(from.getFullYear(), from.getMonth(), from.getDate());
        var end = Date.UTC(to.getFullYear(), to.getMonth(), to.getDate());

        return Math.round((end - start) / 86400000);
      }

      function weekdayName(date) {
        var index = date.getDay() === 0 ? 6 : date.getDay() - 1;

        return weekdays[index];
      }

      function fits(start, untilDate, stepDays) {
        var days = dayDiff(start, untilDate);

        return days >= 0 && iso(untilDate) <= until.getAttribute('max') && days % stepDays === 0;
      }

      function clearReason(start, untilDate, stepDays) {
        var days = dayDiff(start, untilDate);
        if (days < 0) {
          return 'Дата окончания сброшена: она раньше даты слота.';
        }
        if (iso(untilDate) > until.getAttribute('max')) {
          return 'Дата окончания сброшена: не позже ' + untilNote.getAttribute('data-limit') + '.';
        }
        if (stepDays === 14 && days % 7 === 0) {
          return 'Дата окончания сброшена: этот день между занятиями. При повторе каждые 2 недели шаг — 14 дней.';
        }
        if (stepDays === 7) {
          return 'Дата окончания сброшена: нужен тот же день недели, что и дата слота (' + weekdayName(start) + ').';
        }

        return 'Дата окончания сброшена: консультация в этот день не проводится.';
      }

      function hideCleared() {
        cleared.hidden = true;
        cleared.textContent = '';
      }

      function showCleared(text) {
        cleared.hidden = false;
        cleared.textContent = text;
      }

      function monthTitle(year, month) {
        var name = months[month];

        return name.charAt(0).toUpperCase() + name.slice(1) + ' ' + year;
      }

      function renderCal() {
        var start = parseIso(dateInput.value);
        var stepDays = repeatStep();
        if (!start || !stepDays) {
          cal.hidden = true;

          return;
        }
        var limit = parseIso(until.getAttribute('max'));
        var startIndex = start.getFullYear() * 12 + start.getMonth();
        var limitIndex = limit.getFullYear() * 12 + limit.getMonth();
        if (!endView) {
          var focus = parseIso(until.value) || start;
          endView = { y: focus.getFullYear(), m: focus.getMonth() };
        }
        var index = endView.y * 12 + endView.m;
        if (index < startIndex) {
          endView = { y: start.getFullYear(), m: start.getMonth() };
        } else if (index > limitIndex) {
          endView = { y: limit.getFullYear(), m: limit.getMonth() };
        }
        cal.hidden = false;
        cal.querySelector('[data-end-title]').textContent = monthTitle(endView.y, endView.m);
        cal.querySelector('[data-end-prev]').disabled = endView.y === start.getFullYear() && endView.m === start.getMonth();
        cal.querySelector('[data-end-next]').disabled = endView.y === limit.getFullYear() && endView.m === limit.getMonth();
        var cursor = new Date(endView.y, endView.m, 1);
        cursor.setDate(1 - ((cursor.getDay() + 6) % 7));
        var html = '';
        var week;
        var day;
        for (week = 0; week < 6; week++) {
          var row = '';
          var inMonth = false;
          for (day = 0; day < 7; day++) {
            var key = iso(cursor);
            var shown = cursor.getMonth() === endView.m && cursor.getFullYear() === endView.y;
            if (shown) {
              inMonth = true;
              if (fits(start, cursor, stepDays)) {
                var selected = until.value === key ? ' is-selected' : ' is-allowed';
                row += '<td class="cal-day"><button type="button" class="end-day' + selected + '" data-date="' + key + '">' + cursor.getDate() + '</button></td>';
              } else {
                row += '<td class="cal-day"><button type="button" class="end-day" data-date="' + key + '" disabled>' + cursor.getDate() + '</button></td>';
              }
            } else {
              row += '<td class="cal-day out"><span>' + cursor.getDate() + '</span></td>';
            }
            cursor.setDate(cursor.getDate() + 1);
          }
          if (inMonth) {
            html += '<tr>' + row + '</tr>';
          }
        }
        cal.querySelector('[data-end-days]').innerHTML = html;
      }

      function enforceUntil(clearIfBad) {
        var stepDays = repeatStep();
        var start = parseIso(dateInput.value);
        until.required = stepDays > 0;
        if (!stepDays) {
          untilNote.textContent = untilNote.getAttribute('data-off');
          until.readOnly = false;
          until.removeAttribute('min');
          until.removeAttribute('step');
          cal.hidden = true;
          hideCleared();

          return;
        }
        if (!start) {
          untilNote.textContent = untilNote.getAttribute('data-on');
          until.readOnly = false;
          until.removeAttribute('min');
          until.removeAttribute('step');
          cal.hidden = true;

          return;
        }
        until.min = iso(start);
        until.step = String(stepDays);
        until.readOnly = true;
        var chosen = parseIso(until.value);
        if (chosen && !fits(start, chosen, stepDays)) {
          if (clearIfBad) {
            showCleared(clearReason(start, chosen, stepDays));
            until.value = '';
          }
        } else if (clearIfBad || chosen) {
          hideCleared();
        }
        var from = formatRu(start);
        var limitLabel = untilNote.getAttribute('data-limit');
        if (stepDays === 14) {
          untilNote.textContent = 'Только даты через 14, 28, … дней от ' + from + ', по ' + limitLabel + '. День через 7 дней не подходит.';
        } else {
          untilNote.textContent = 'Только ' + weekdayName(start) + ', как у даты слота, с ' + from + ' по ' + limitLabel + '.';
        }
        renderCal();
      }

      function sync(clearUntil) {
        var online = form.querySelector('input[name="format"]:checked').value === 'online';
        inPerson.hidden = online;
        building.disabled = online;
        room.disabled = online;
        building.required = !online;
        room.required = !online;
        url.required = online;
        urlLabel.textContent = online ? 'Ссылка на встречу' : 'Ссылка на встречу, необязательно';
        enforceUntil(clearUntil === true);
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

      var warningTimer = null;
      var warningSeq = 0;

      function applyWarning(html) {
        var current = form.querySelector('[data-room-warning]');
        var holder = document.createElement('div');
        holder.innerHTML = html.trim();
        var next = holder.firstElementChild;
        if (!current || !next) {
          return;
        }
        current.replaceWith(next);
      }

      function readWarning() {
        var seq = ++warningSeq;
        var online = form.querySelector('input[name="format"]:checked').value === 'online';
        var repeatInput = form.querySelector('input[name="repeat"]:checked');
        var params = new URLSearchParams();
        params.set('format', online ? 'online' : 'in_person');
        params.set('building', building.value);
        params.set('room', room.value.trim());
        params.set('date', form.querySelector('#date').value);
        params.set('start_time', form.querySelector('#start_time').value);
        params.set('end_time', form.querySelector('#end_time').value);
        params.set('repeat', repeatInput ? repeatInput.value : 'none');
        params.set('repeat_until', until.value);
        fetch(form.getAttribute('data-room-warning-url') + '?' + params.toString(), {
          headers: { 'Accept': 'text/html' },
          credentials: 'same-origin'
        }).then(function (response) {
          if (!response.ok) {
            return '';
          }
          return response.text();
        }).then(function (html) {
          if (seq !== warningSeq || !html) {
            return;
          }
          applyWarning(html);
        }).catch(function () {});
      }

      function scheduleWarning() {
        window.clearTimeout(warningTimer);
        warningTimer = window.setTimeout(readWarning, 200);
      }

      dateInput.addEventListener('change', function () {
        endView = null;
        enforceUntil(true);
      });
      until.addEventListener('change', function () {
        enforceUntil(true);
      });
      ['#date', '#start_time', '#end_time', '#building', '#room', '#repeat_until'].forEach(function (selector) {
        var input = form.querySelector(selector);
        input.addEventListener('change', scheduleWarning);
        input.addEventListener('input', scheduleWarning);
      });
      form.querySelectorAll('input[name="format"]').forEach(function (input) {
        input.addEventListener('change', function () {
          sync(false);
          scheduleWarning();
        });
      });
      form.querySelectorAll('input[name="repeat"]').forEach(function (input) {
        input.addEventListener('change', function () {
          endView = null;
          sync(true);
          scheduleWarning();
        });
      });
      cal.addEventListener('click', function (event) {
        var button = event.target.closest('button[data-date]');
        if (!button || button.disabled) {
          return;
        }
        until.value = button.getAttribute('data-date');
        hideCleared();
        until.dispatchEvent(new Event('change', { bubbles: true }));
      });
      cal.querySelector('[data-end-prev]').addEventListener('click', function () {
        if (!endView || this.disabled) {
          return;
        }
        endView.m -= 1;
        if (endView.m < 0) {
          endView.m = 11;
          endView.y -= 1;
        }
        renderCal();
      });
      cal.querySelector('[data-end-next]').addEventListener('click', function () {
        if (!endView || this.disabled) {
          return;
        }
        endView.m += 1;
        if (endView.m > 11) {
          endView.m = 0;
          endView.y += 1;
        }
        renderCal();
      });
      sync(false);
    })();
  </script>
@endsection
