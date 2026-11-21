@php
  $jumpRaw = request()->query('on');
  $jumpValue = is_string($jumpRaw) ? $jumpRaw : '';
@endphp
<form class="week-range" method="get" action="{{ $action }}">
  <input type="hidden" name="jump" value="1">
  @foreach (($hidden ?? []) as $name => $value)
    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
  @endforeach
  <a class="cal-arrow" data-tip href="{{ $previous }}" rel="prev" aria-label="Предыдущая неделя">
    <span aria-hidden="true">‹</span>
    <span class="tip" role="tooltip">Предыдущая неделя</span>
  </a>
  <p class="week-current">{{ $span->label() }}</p>
  <a class="cal-arrow" data-tip href="{{ $next }}" rel="next" aria-label="Следующая неделя">
    <span aria-hidden="true">›</span>
    <span class="tip" role="tooltip">Следующая неделя</span>
  </a>
  <div class="week-jump">
    <label class="field" for="week-on">
      <span>Перейти к дате</span>
      <input id="week-on" type="date" name="on" value="{{ $jumpValue }}" @if ($span->error) aria-invalid="true" aria-describedby="week-range-error" @endif>
    </label>
    <button class="cal-arrow" data-tip type="submit" aria-label="Открыть неделю этой даты">
      <span aria-hidden="true">›</span>
      <span class="tip" role="tooltip">Открыть неделю этой даты</span>
    </button>
  </div>
</form>
@if ($span->error)
  <p class="notice error" id="week-range-error">{{ $span->error }}</p>
@endif
