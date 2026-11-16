<form class="week-range" method="get" action="{{ $action }}">
  <label class="field">
    <span>С</span>
    <input type="date" name="from" value="{{ $span->from->toDateString() }}">
  </label>
  <label class="field">
    <span>По</span>
    <input type="date" name="to" value="{{ $span->query()['to'] }}">
  </label>
  <button class="btn" type="submit">Показать</button>
  <a class="btn btn-ghost" href="{{ $action }}?{{ http_build_query($span->previous()->query()) }}">Предыдущая неделя</a>
  <a class="btn btn-ghost" href="{{ $action }}?{{ http_build_query($span->next()->query()) }}">Следующая неделя</a>
</form>
@if ($span->error)
  <p class="notice error">{{ $span->error }}</p>
@endif
