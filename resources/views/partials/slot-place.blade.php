<div class="where">
  @if ($slot->isOnline())
    <p class="where-line">
      <span class="where-label">Ссылка на встречу</span>
      <a class="place-link" href="{{ $slot->meeting_url }}" rel="noopener noreferrer">{{ $slot->meeting_url }}</a>
    </p>
  @else
    <p class="where-line">
      <span class="where-label">Место</span>
      <span>{{ $slot->place() }}</span>
    </p>
    @if ($slot->hasMeetingLink())
      <p class="where-line">
        <span class="where-label">Ссылка на встречу</span>
        <a class="place-link" href="{{ $slot->meeting_url }}" rel="noopener noreferrer">{{ $slot->meeting_url }}</a>
      </p>
    @endif
  @endif
</div>
