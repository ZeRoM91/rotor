@extends('layout')

@section('title')
    Заметка для {{ $ignore->ignoring->login }}
@stop

@section('content')

    <h1>Заметка для {{ $ignore->ignoring->login }}</h1>

    <div class="form">
        <form method="post" action="/ignore/note/{{ $ignore->id }}">
            <input type="hidden" name="token" value="{{ $_SESSION['token'] }}">

            <div class="form-group{{ hasError('msg') }}">
                <label for="markItUp">Заметка:</label>
                <textarea class="form-control" id="markItUp" rows="5" name="msg">{{ getInput('msg', $ignore->text) }}</textarea>
                {!! textError('msg') !!}
            </div>

            <button class="btn btn-primary">Редактировать</button>
        </form>
    </div>
    <br>

    <i class="fa fa-arrow-circle-left"></i> <a href="/ignore">Вернуться</a><br>
@stop
