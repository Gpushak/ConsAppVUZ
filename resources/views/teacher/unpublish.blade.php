@extends('layouts.app')

@section('title', 'Снять с публикации')

@section('content')
  <div class="page-head">
    <div>
      <h1>Снять с публикации</h1>
      <p class="lead">{{ $slot->dateLabel() }}, {{ $slot->timeRange() }}, @include('partials.slot-place', ['slot' => $slot]).</p>
    </div>
  </div>
  @include('partials.errors')
  <p class="notice">Слот исчезнет из кабинета студента. Вернуть его в публикацию нельзя.</p>
  <form method="POST" action="{{ route('teacher.slots.unpublish', $slot) }}">
    @csrf
    <p class="form-actions"><button class="btn" type="submit">Снять с публикации</button></p>
  </form>
  <p class="hint"><a href="{{ route('teacher.slots.index') }}">К моим слотам</a></p>
@endsection
