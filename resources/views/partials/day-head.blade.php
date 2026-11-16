<h2 class="day-head" id="day-{{ $date->format('Ymd') }}">
  <span class="num">{{ $date->format('j') }}</span>
  <span class="dow">{{ \App\Support\DateSpan::weekday($date) }}<span class="iso">{{ $date->format('d.m.Y') }}</span></span>
</h2>
