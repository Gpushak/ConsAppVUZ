<div id="room-warning" data-room-warning aria-live="polite" @if ($roomConflicts->isEmpty()) hidden @else class="notice room-warning" role="status" @endif>
  @if ($roomConflicts->isNotEmpty())
    <p>Аудитория уже занята в это время. Слот всё равно можно опубликовать.</p>
    <ul>
      @foreach ($roomConflicts as $conflict)
        <li>{{ $conflict['teacher'] }}, {{ $conflict['date'] }}, <span class="room-when">{{ $conflict['from'] }}–{{ $conflict['to'] }}</span></li>
      @endforeach
    </ul>
  @endif
</div>
