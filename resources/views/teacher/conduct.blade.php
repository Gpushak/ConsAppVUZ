@extends('layouts.app')

@section('title', 'Консультация проведена')

@section('content')
  <div class="page-head">
    <div>
      <h1>Отметить проведённой</h1>
      <p class="lead">{{ $slot->dateLabel() }}, {{ $slot->timeRange() }}, {{ $slot->place() }}. Активные записи получат статус «проведён».</p>
    </div>
  </div>
  @include('partials.errors')
  <form class="panel" method="POST" action="{{ route('teacher.slots.conduct.store', $slot) }}">
    @csrf
    <div class="field">
      <label for="comment">Комментарий, необязательно</label>
      <textarea id="comment" name="comment" maxlength="500">{{ old('comment') }}</textarea>
    </div>
    <p class="form-actions"><button class="btn" type="submit">Отметить проведённой</button></p>
  </form>
  <p class="hint"><a href="{{ route('teacher.slots.index') }}">К моим слотам</a></p>
@endsection
