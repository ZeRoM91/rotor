<?php
App::view($config['themes'].'/index');

$act = (isset($_GET['act'])) ? check($_GET['act']) : 'index';

show_title('Угадай число');

$randgame = mt_rand(1, 100);

if (is_user()) {
    if (isset($_GET['newgame']) || empty($_SESSION['hill'])) {
        $_SESSION['hill'] = $randgame;
        $_SESSION['hi_count'] = 0;
    }

    switch ($act):
    ############################################################################################
    ##                                    Главная страница                                    ##
    ############################################################################################
        case 'index':

            echo '<b>Введите число от 1 до 100</b><br /><br />';
            echo '<b>Попыток: 0</b><br />';

            echo '<div class="form">';
            echo '<form action="/games/hi?act=hi" method="post">';
            echo 'Введите число:<br />';
            echo '<input type="text" name="guess" />';
            echo '<input type="submit" value="Угадать" />';
            echo '</form></div><br />';

            echo 'У вас в наличии: '.moneys($udata['users_money']).'<br /><br />';

            echo '<i class="fa fa-question-circle"></i> <a href="/games/hi?act=faq">Правила</a><br />';
        break;

        ############################################################################################
        ##                                          Игра                                          ##
        ############################################################################################
        case 'hi':

            $guess = abs(intval($_POST['guess']));

            if ($udata['users_money'] >= $config['hisumma']) {
                if ($guess >= 1 && $guess <= 100) {
                    $_SESSION['hi_count']++;

                    if ($guess != $_SESSION['hill']) {
                        if ($_SESSION['hi_count'] < $config['hipopytka']) {
                            echo'<b>Введите число от 1 до 100</b><br /><br />';

                            echo '<b>Попыток: '.(int)$_SESSION['hi_count'].'</b><br />';

                            if ($guess > $_SESSION['hill']) {
                                echo $guess.' — это большое число<br /><i class="fa fa-minus-circle"></i> Введите меньше<br /><br />';
                            }
                            if ($guess < $_SESSION['hill']) {
                                echo $guess.' — это маленькое число<br /><i class="fa fa-plus-circle"></i> Введите больше<br /><br />';
                            }

                            echo '<div class="form">';
                            echo '<form action="/games/hi?act=hi" method="post">';
                            echo 'Введите число:<br />';
                            echo '<input type="text" name="guess" />';
                            echo '<input type="submit" value="Угадать" />';
                            echo '</form></div><br />';

                            DB::run() -> query("UPDATE `users` SET `users_money`=`users_money`- ".$config['hisumma']." WHERE `users_login`=? LIMIT 1;", array($log));

                            $count_pop = $config['hipopytka'] - $_SESSION['hi_count'];

                            echo 'Осталось попыток: <b>'.(int)$count_pop.'</b><br />';

                            $allmoney = DB::run() -> querySingle("SELECT `users_money` FROM `users` WHERE `users_login`=? LIMIT 1;", array($log));

                            echo 'У вас в наличии: '.moneys($allmoney).'<br /><br />';
                        } else {
                            echo '<i class="fa fa-times"></i> <b>Вы проигали потому что, не отгадали число за '.(int)$config['hipopytka'].' попыток</b><br />';
                            echo 'Было загадано число: '.$_SESSION['hill'].'<br /><br />';

                            unset($_SESSION['hill']);
                            unset($_SESSION['hi_count']);
                        }
                    } else {
                        DB::run() -> query("UPDATE `users` SET `users_money`=`users_money`+? WHERE `users_login`=? LIMIT 1;", array($config['hiprize'], $log));

                        echo '<b>Поздравляем!!! Вы угадали число '.(int)$guess.'</b><br />';
                        echo 'Ваш выигрыш составил '.moneys($config['hiprize']).'<br /><br />';

                        unset($_SESSION['hill']);
                        unset($_SESSION['hi_count']);
                    }
                } else {
                    show_error('Ошибка! Необходимо ввести число в пределах разрешенного диапазона!');
                }
            } else {
                show_error('Вы не можете играть, т.к. на вашем счету недостаточно средств!');
            }

            echo '<i class="fa fa-arrow-circle-up"></i> <a href="/games/hi?newgame">Начать заново</a><br />';
        break;

        ############################################################################################
        ##                                    Правила игры                                        ##
        ############################################################################################
        case 'faq':

            echo 'Для участия в игре напишите число и нажмите "Угадать", за каждую попытку у вас будут списывать по '.moneys($config['hisumma']).'<br />';
            echo 'После каждой попытки вам дают подсказку большое это число или маленькое<br />';
            echo 'Если вы не уложились за '.$config['hipopytka'].' попыток, то игра будет начата заново<br />';
            echo 'При выигрыше вы получаете на счет '.moneys($config['hiprize']).'<br />';
            echo 'Итак дерзайте!<br /><br />';

            echo '<i class="fa fa-arrow-circle-left"></i> <a href="/games/hi">Вернуться</a><br />';
        break;

    endswitch;

} else {
    show_login('Вы не авторизованы, чтобы начать игру, необходимо');
}

echo '<i class="fa fa-money"></i> <a href="/games">Развлечения</a><br />';

App::view($config['themes'].'/foot');
