<form class="week-range" method="get" action="{{ $action }}">
  <label class="field">
    <span>Неделя с</span>
    <input type="date" name="from" value="{{ $span->from->toDateString() }}" @if ($span->error) aria-invalid="true" aria-describedby="week-range-error" @endif>
  </label>
  <label class="field">
    <span>Неделя по</span>
    <input type="date" name="to" value="{{ $span->query()['to'] }}" @if ($span->error) aria-invalid="true" aria-describedby="week-range-error" @endif>
  </label>
  <button class="btn" type="submit">Показать</button>
  <a class="btn btn-ghost" href="{{ $action }}?{{ http_build_query($span->previous()->query()) }}">Предыдущая неделя</a>
  <a class="btn btn-ghost" href="{{ $action }}?{{ http_build_query($span->next()->query()) }}">Следующая неделя</a>
</form>
@if ($span->error)
  <p class="notice error" id="week-range-error">{{ $span->error }}</p>
@endif
