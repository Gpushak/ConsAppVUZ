@extends('layouts.app')

@section('title', 'Остановить повтор')

@section('content')
  <div class="page-head">
    <h1>Остановить повтор</h1>
  </div>
  @include('partials.errors')
  <p class="notice">Будущие слоты этого повтора без записей будут сняты с публикации. Занятые и уже начавшиеся останутся. Один день по-прежнему снимается отдельно.</p>
  <form method="POST" action="{{ route('teacher.slots.series.stop.store', $slot) }}">
    @csrf
    <p class="form-actions"><button class="btn" type="submit">Остановить повтор</button></p>
  </form>
  <p class="hint"><a href="{{ route('teacher.slots.index') }}">К моим слотам</a></p>
@endsection
