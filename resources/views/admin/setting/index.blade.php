@extends('layout')

@section('title')
    Настройки сайта
@stop

@section('content')

    <h1>Настройки сайта</h1>

    <div class="row">
        <div class="col-4 bg-light p-1">
            <div class="nav flex-column nav-pills">
                <a class="nav-link" href="/admin/setting?act=main" id="main">Основные настройки</a>
                <a class="nav-link" href="/admin/setting?act=mail" id="mail">Почта / Рассылка</a>
                <a class="nav-link" href="/admin/setting?act=info" id="info">Вывод информации</a>
                <a class="nav-link" href="/admin/setting?act=guest" id="guest">Гостевая / Новости</a>
                <a class="nav-link" href="/admin/setting?act=forum" id="forum">Форум / Галерея</a>
                <a class="nav-link" href="/admin/setting?act=bookmark" id="bookmark">Закладки / Голосования / Приват</a>
                <a class="nav-link" href="/admin/setting?act=load" id="load">Загруз-центр</a>
                <a class="nav-link" href="/admin/setting?act=blog" id="blog">Блоги</a>
                <a class="nav-link" href="/admin/setting?act=page" id="page">Постраничная навигация</a>
                <a class="nav-link" href="/admin/setting?act=other" id="other">Прочее / Другое</a>
                <a class="nav-link" href="/admin/setting?act=protect" id="protect">Защита / Безопасность</a>
                <a class="nav-link" href="/admin/setting?act=price" id="price">Стоимость и цены</a>
                <a class="nav-link" href="/admin/setting?act=advert" id="advert">Реклама на сайте</a>
                <a class="nav-link" href="/admin/setting?act=image" id="image">Загрузка изображений</a>
                <a class="nav-link" href="/admin/setting?act=smile" id="smile">Смайлы</a>
                <a class="nav-link" href="/admin/setting?act=offer" id="offer">Предложения и проблемы</a>
            </div>
        </div>
        <div class="col-8">
            @include ('admin/setting/_' . $act)
        </div>
    </div>

    <br><i class="fa fa-wrench"></i> <a href="/admin">В админку</a><br>
@stop

@push('scripts')
    <script>
        $(function () {
            $('#{{ $act }}').tab('show');
        })
    </script>
@endpush
