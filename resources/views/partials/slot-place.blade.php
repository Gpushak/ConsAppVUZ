@if ($slot->isOnline())
  <a class="place-link" href="{{ $slot->meeting_url }}" rel="noopener noreferrer">{{ $slot->meeting_url }}</a>
@else
  <span>{{ $slot->place() }}</span>
  @if ($slot->hasMeetingLink())
    <a class="place-link extra" href="{{ $slot->meeting_url }}" rel="noopener noreferrer">{{ $slot->meeting_url }}</a>
  @endif
@endif
