@extends('layouts.app')

@section('title', 'Новый слот')

@section('content')
  <div class="page-head">
    <div>
      <h1>Новый слот</h1>
      <p class="lead">Окно групповое: записаться сможет несколько студентов.</p>
    </div>
  </div>
  <div class="panel">
    @include('partials.errors')
    <form method="POST" action="{{ route('teacher.slots.store') }}">
      @csrf
      <div class="field">
        <label for="date">Дата</label>
        <input id="date" type="text" name="date" value="{{ old('date', now()->addDays(5)->format('d.m.Y')) }}" inputmode="numeric" placeholder="ДД.ММ.ГГГГ" required>
      </div>
      <div class="field">
        <label for="start_time">Начало</label>
        <input id="start_time" type="text" name="start_time" value="{{ old('start_time', '16:00') }}" inputmode="numeric" placeholder="ЧЧ:ММ" required>
      </div>
      <div class="field">
        <label for="end_time">Конец</label>
        <input id="end_time" type="text" name="end_time" value="{{ old('end_time', '16:45') }}" inputmode="numeric" placeholder="ЧЧ:ММ" required>
      </div>
      <div class="field">
        <label for="building">Корпус</label>
        <select id="building" name="building" size="7" required>
          @foreach (\App\Support\Building::LIST as $building)
            <option value="{{ $building }}" @selected(old('building', 'Главный Б') === $building)>{{ $building }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label for="room">Аудитория</label>
        <input id="room" type="text" name="room" value="{{ old('room', '220') }}" required>
      </div>
      <div class="field">
        <label for="comment">Комментарий, необязательно</label>
        <textarea id="comment" name="comment" maxlength="500">{{ old('comment', 'принести черновик курсовой') }}</textarea>
      </div>
      <div class="field">
        <label for="meeting_url">Ссылка, необязательно</label>
        <input id="meeting_url" type="url" name="meeting_url" value="{{ old('meeting_url') }}" placeholder="https://">
      </div>
      <p class="form-actions"><button class="btn" type="submit">Опубликовать</button></p>
    </form>
  </div>
@endsection
