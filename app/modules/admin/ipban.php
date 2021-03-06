<?php
view(setting('themes').'/index');

if (isset($_GET['act'])) {
    $act = check($_GET['act']);
} else {
    $act = 'index';
}
$page = abs(intval(Request::input('page', 1)));

if (isAdmin([101, 102])) {
    //show_title('IP-бан панель');

    switch ($action):
    ############################################################################################
    ##                                    Главная страница                                    ##
    ############################################################################################
        case 'index':
            echo '<a href="/admin/log?code=666">История автобанов</a><br>';

            $total = DB::run() -> querySingle("SELECT count(*) FROM `ban`;");
            $page = paginate(setting('ipbanlist'), $total);

            if ($total > 0) {

                $queryban = DB::select("SELECT * FROM `ban` ORDER BY `time` DESC LIMIT ".$page['offset'].", ".setting('ipbanlist').";");

                echo '<form action="/admin/ipban?act=del&amp;page='.$page['current'].'&amp;uid='.$_SESSION['token'].'" method="post">';

                while ($data = $queryban -> fetch()) {
                    echo '<div class="b">';
                    echo '<input type="checkbox" name="del[]" value="'.$data['id'].'"> ';
                    echo '<i class="fa fa-file"></i> <b>'.$data['ip'].'</b></div>';

                    echo '<div>Добавлено: ';

                    if (!empty($data['user'])) {
                        echo '<b>'.profile($data['user']).'</b><br>';
                    } else {
                        echo '<b>Автоматически</b><br>';
                    }

                    echo 'Время: '.dateFixed($data['time']).'</div>';
                }

                echo '<br><input type="submit" value="Удалить выбранное"></form>';

                pagination($page);

                echo 'Всего заблокировано: <b>'.$total.'</b><br><br>';
            } else {
                showError('В бан-листе пока пусто!');
            }

            echo '<div class="form">';
            echo '<form action="/admin/ipban?act=add&amp;page='.$page['current'].'&amp;uid='.$_SESSION['token'].'" method="post">';
            echo 'IP-адрес:<br>';
            echo '<input type="text" name="ips">';
            echo '<input value="Добавить" type="submit"></form></div><br>';

            echo 'Примеры банов: 127.0.0.1 без отступов и пробелов<br>';
            echo 'Или по маске 127.0.0.* , 127.0.*.* , будут забанены все IP совпадающие по начальным цифрам<br><br>';

            if ($total > 0 && isAdmin([101])) {
                echo '<i class="fa fa-times"></i> <a href="/admin/ipban?act=clear&amp;uid='.$_SESSION['token'].'">Очистить список</a><br>';
            }
        break;

        ############################################################################################
        ##                                   Занесение в список                                   ##
        ############################################################################################
        case 'add':

            $uid = check($_GET['uid']);
            $ips = check($_POST['ips']);

            if ($uid == $_SESSION['token']) {
                if (preg_match('|^[0-9]{1,3}\.[0-9,*]{1,3}\.[0-9,*]{1,3}\.[0-9,*]{1,3}$|', $ips)) {
                    $banip = DB::run() -> querySingle("SELECT `id` FROM `ban` WHERE `ip`=? LIMIT 1;", [$ips]);
                    if (empty($banip)) {
                        DB::insert("INSERT INTO ban (`ip`, `user`, `time`) VALUES (?, ?, ?);", [$ips, getUser('login'), SITETIME]);
                        ipBan(true);

                        setFlash('success', 'IP успешно занесен в список!');
                        redirect("/admin/ipban?page=$page");
                    } else {
                        showError('Ошибка! Введенный IP уже имеетеся в списке!');
                    }
                } else {
                    showError('Ошибка! Вы ввели недопустимый IP-адрес для бана!');
                }
            } else {
                showError('Ошибка! Неверный идентификатор сессии, повторите действие!');
            }

            echo '<i class="fa fa-arrow-circle-left"></i> <a href="/admin/ipban">Вернуться</a><br>';
        break;

        ############################################################################################
        ##                                   Удаление из списка                                   ##
        ############################################################################################
        case 'del':

            $uid = check($_GET['uid']);
            if (isset($_POST['del'])) {
                $del = intar($_POST['del']);
            } else {
                $del = 0;
            }

            if ($uid == $_SESSION['token']) {
                if (!empty($del)) {
                    $del = implode(',', $del);

                    DB::delete("DELETE FROM `ban` WHERE `id` IN (".$del.");");
                    ipBan(true);

                    setFlash('success', 'Выбранные IP успешно удалены из списка!');
                    redirect("/admin/ipban?page=$page");
                } else {
                    echo '<i class="fa fa-times"></i> <b>Ошибка удаления! Отсутствуют выбранные IP</b><br>';
                }
            } else {
                echo '<i class="fa fa-times"></i> <b>Ошибка! Неверный идентификатор сессии, повторите действие!</b><br>';
            }

            echo '<i class="fa fa-arrow-circle-left"></i> <a href="/admin/ipban?page='.$page.'">Вернуться</a><br>';
        break;

        ############################################################################################
        ##                                     Очистка списка                                     ##
        ############################################################################################
        case 'clear':

            $uid = check($_GET['uid']);

            if (isAdmin([101])) {
                if ($uid == $_SESSION['token']) {
                    DB::run() -> query("TRUNCATE `ban`;");
                    ipBan(true);

                    setFlash('success', 'Список IP успешно очищен!');
                    redirect("/admin/ipban");
                } else {
                    showError('Ошибка! Неверный идентификатор сессии, повторите действие!');
                }
            } else {
                showError('Ошибка! Очищать бан-лист могут только суперадмины!');
            }

            echo '<i class="fa fa-arrow-circle-left"></i> <a href="/admin/ipban?page='.$page.'">Вернуться</a><br>';
        break;

    endswitch;

    echo '<i class="fa fa-wrench"></i> <a href="/admin">В админку</a><br>';

} else {
    redirect("/");
}

view(setting('themes').'/foot');
