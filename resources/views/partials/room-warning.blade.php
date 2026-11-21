<div id="room-warning" data-room-warning role="status" aria-live="polite">
  @if ($roomConflicts->isNotEmpty())
    <div class="notice room-warning">
      <p>Аудитория уже занята в это время. Слот всё равно можно опубликовать.</p>
      <ul>
        @foreach ($roomConflicts as $conflict)
          <li>{{ $conflict['teacher'] }}, {{ $conflict['date'] }}, <span class="room-when">{{ $conflict['from'] }}–{{ $conflict['to'] }}</span></li>
        @endforeach
      </ul>
    </div>
  @endif
</div>
