@extends('layout')

@section('title')
    История авторизаций
@stop

@section('content')

    <h1>История авторизаций</h1>

    @if ($logins->isNotEmpty())
        @foreach($logins as $data)
            <div class="b">
                @if ($data->type)
                    <i class="fa fa-sign-in-alt"></i> <b>Авторизация</b>
                @else
                    <i class="fa fa-sync"></i> <b>Автовход</b>
                @endif

                <small>({{ dateFixed($data->created_at) }})</small>
            </div>
            <div>
                <span class="data">
                    Browser: {{ $data->brow }} /
                    IP: {{ $data->ip }}
                </span>
            </div>
        @endforeach

        {!! pagination($page) !!}
    @else
        {!! showError('История авторизаций отсутствует') !!}
    @endif
@stop
