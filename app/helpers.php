<?php

use App\Classes\BBCode;
use App\Classes\FileUpload;
use App\Classes\Registry;
use App\Classes\Request;
use App\Models\Antimat;
use App\Models\Ban;
use App\Models\Banhist;
use App\Models\BlackList;
use App\Models\Blog;
use App\Models\Bookmark;
use App\Models\Cats;
use App\Models\Chat;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Counter;
use App\Models\Down;
use App\Models\Guest;
use App\Models\Ignore;
use App\Models\Inbox;
use App\Models\Invite;
use App\Models\Log;
use App\Models\Login;
use App\Models\News;
use App\Models\Note;
use App\Models\Notebook;
use App\Models\Notice;
use App\Models\Offer;
use App\Models\Online;
use App\Models\Outbox;
use App\Models\Photo;
use App\Models\Post;
use App\Models\Rating;
use App\Models\RekUser;
use App\Models\Setting;
use App\Models\Smile;
use App\Models\Spam;
use App\Models\Topic;
use App\Models\User;
use App\Models\Visit;
use App\Models\Vote;
use App\Models\Wall;
use Illuminate\Database\Capsule\Manager as DB;
use Jenssegers\Blade\Blade;

/**
 * Форматирует вывод времени из секунд
 *
 * @param  int    $time секунды
 * @return string       форматированный вывод
 */
function makeTime($time)
{
    if ($time < 3600) {
        $time = sprintf("%02d:%02d", (int)($time / 60) % 60, $time % 60);
    } else {
        $time = sprintf("%02d:%02d:%02d", (int)($time / 3600) % 24, (int)($time / 60) % 60, $time % 60);
    }

    return $time;
}

/**
 * Форматирует время с учетом часовых поясов
 *
 * @param  int     $timestamp секунды
 * @param  string  $format    формат времени
 * @return string             форматированный вывод
 */
function dateFixed($timestamp, $format = "d.m.y / H:i")
{
    if (!is_numeric($timestamp)) {
        $timestamp = SITETIME;
    }

    $shift = user('timezone') * 3600;
    $dateStamp = date($format, $timestamp + $shift);

    $today = date("d.m.y", SITETIME + $shift);
    $yesterday = date("d.m.y", strtotime("-1 day", SITETIME + $shift));

    $dateStamp = str_replace($today, 'Сегодня', $dateStamp);
    $dateStamp = str_replace($yesterday, 'Вчера', $dateStamp);

    $search = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $replace = ['Января', 'Февраля', 'Марта', 'Апреля', 'Мая', 'Июня', 'Июля', 'Августа', 'Сентября', 'Октября', 'Ноября', 'Декабря'];
    $dateStamp = str_replace($search, $replace, $dateStamp);

    return $dateStamp;
}

/**
 * Удаляет изображение и превью
 *
 * @param string $dir   директория с изображение
 * @param string $image имя изображения
 */
function deleteImage($dir, $image)
{
    $path = str_replace('/', '_', $dir.$image);

    if (file_exists(HOME.'/'.$dir.$image)) {
        unlink(HOME.'/'.$dir.$image);
    }

    if (file_exists(HOME.'/uploads/thumbnail/'.$path)) {
        unlink(HOME.'/uploads/thumbnail/'.$path);
    }
}

/**
 * Удаляет записи пользователя из всех таблиц
 *
 * @param  User    $user
 * @return boolean       результат удаления
 */
function deleteUser(User $user)
{
    deleteImage('uploads/photos/', $user['picture']);
    deleteImage('uploads/avatars/', $user['avatar']);

    Inbox::query()->where('user_id', $user->id)->delete();
    Outbox::query()->where('user_id', $user->id)->delete();
    Contact::query()->where('user_id', $user->id)->delete();
    Ignore::query()->where('user_id', $user->id)->delete();
    Rating::query()->where('user_id', $user->id)->delete();
    Visit::query()->where('user_id', $user->id)->delete();
    Wall::query()->where('user_id', $user->id)->delete();
    Note::query()->where('user_id', $user->id)->delete();
    Notebook::query()->where('user_id', $user->id)->delete();
    Banhist::query()->where('user_id', $user->id)->delete();
    Bookmark::query()->where('user_id', $user->id)->delete();
    Login::query()->where('user_id', $user->id)->delete();
    Invite::query()->where('user_id', $user->id)->orWhere('invite_user_id', $user->id)->delete();

    return $user->delete();
}

/**
 * Удаляет альбом пользователя
 *
 * @param  User $user
 * @return void
 */
function deleteAlbum(User $user)
{
    $photos = Photo::query()->where('user_id', $user->id)->get();

    if ($photos->isNotEmpty()) {
        foreach ($photos as $photo) {

            Comment::query()
                ->where('relate_type', Photo::class)
                ->where('relate_id', $photo->id)
                ->delete();

            Photo::query()->where('id', $photo->id)->delete();

            deleteImage('uploads/pictures/', $photo->link);
        }
    }
}

// ------------------ Функция перекодировки из UTF в WIN --------------------//
function utfToWin($str)
{
    return mb_convert_encoding($str, 'windows-1251', 'utf-8');
}

// ------------------ Функция перекодировки из WIN в UTF --------------------//
function winToUtf($str)
{
    return mb_convert_encoding($str, 'utf-8', 'windows-1251');
}

// ------------------ Функция преобразования в нижний регистр для UTF ------------------//
function utfLower($str)
{
    return mb_strtolower($str, 'utf-8');
}

// ---------- Аналог функции substr для UTF-8 ---------//
function utfSubstr($str, $offset, $length = null)
{
    if ($length === null) {
        $length = utfStrlen($str);
    }

    return mb_substr($str, $offset, $length, 'utf-8');
}

// ---------------------- Аналог функции strlen для UTF-8 -----------------------//
function utfStrlen($str)
{
    return mb_strlen($str, 'utf-8');
}

// ----------------------- Функция определения кодировки ------------------------//
function isUtf($str)
{
    return mb_check_encoding($str, 'utf-8');
}

// ----------------------- Функция экранирования основных знаков --------------------------//
function check($msg)
{
    if (is_array($msg)) {
        foreach($msg as $key => $val) {
            $msg[$key] = check($val);
        }
    } else {
        $msg = htmlspecialchars($msg);
        $search = ['|', '\'', '$', '\\', "\0", "\x00", "\x1A", chr(226) . chr(128) . chr(174)];
        $replace = ['&#124;', '&#39;', '&#36;', '&#92;', '', '', '', ''];

        $msg = str_replace($search, $replace, $msg);
        $msg = stripslashes(trim($msg));
    }

    return $msg;
}

// --------------- Функция правильного вывода веса файла -------------------//
function formatSize($file_size)
{
    if ($file_size >= 1048576000) {
        $file_size = round(($file_size / 1073741824), 2)." Gb";
    } elseif ($file_size >= 1024000) {
        $file_size = round(($file_size / 1048576), 2)." Mb";
    } elseif ($file_size >= 1000) {
        $file_size = round(($file_size / 1024), 2)." Kb";
    } else {
        $file_size = round($file_size)." byte";
    }
    return $file_size;
}

// --------------- Функция форматированного вывода размера файла -------------------//
function formatFileSize($file)
{
    if (file_exists($file) && is_file($file)) {
        return formatSize(filesize($file));
    } else {
        return 0;
    }
}

// --------------- Функция правильного вывода времени -------------------//
function formatTime($file_time, $round = 1)
{
    if ($file_time >= 86400) {
        $file_time = round((($file_time / 60) / 60) / 24, $round).' дн.';
    } elseif ($file_time >= 3600) {
        $file_time = round(($file_time / 60) / 60, $round).' час.';
    } elseif ($file_time >= 60) {
        $file_time = round($file_time / 60).' мин.';
    } else {
        $file_time = round($file_time).' сек.';
    }
    return $file_time;
}

// ------------------ Функция антимата --------------------//
function antimat($str)
{
    $words = Antimat::query()
        ->orderBy(DB::raw('CHAR_LENGTH(string)'), 'desc')
        ->pluck('string')
        ->all();

    if ($words) {
        foreach($words as $word) {
            $str = preg_replace('|'.preg_quote($word).'|iu', '***', $str);
        }
    }

    return $str;
}

// ------------------ Функция должности юзера --------------------//
function userLevel($level)
{
    $name = explode(',', setting('statusname'));

    switch ($level) {
        case '101': $status = $name[0];
            break;
        case '102': $status = $name[1];
            break;
        case '103': $status = $name[2];
            break;
        case '105': $status = $name[3];
            break;
        default: $status = $name[4];
    }

    return $status;
}

// ---------------- Функция кэширования статусов ------------------//
function saveStatus($time = 0)
{
    if (empty($time) || @filemtime(STORAGE.'/temp/status.dat') < time() - $time) {

    $users = User::query()
        ->select('users.id', 'users.status', 'status.name', 'status.color')
        ->leftJoin('status', 'users.point', 'between', DB::raw('status.topoint and status.point'))
        ->where('users.point', '>', 0)
        ->get();

        $statuses = [];
        foreach ($users as $user) {
            if (! empty($user['status'])) {
                $statuses[$user['id']] = '<span style="color:#ff0000">'.$user['status'].'</span>';
                continue;
            }

            if (! empty($user['color'])) {
                $statuses[$user['id']] = '<span style="color:'.$user['color'].'">'.$user['name'].'</span>';
                continue;
            }

            $statuses[$user['id']] = $user['name'];
        }

        file_put_contents(STORAGE.'/temp/status.dat', serialize($statuses), LOCK_EX);
    }
}
// ------------- Функция вывода статусов пользователей -----------//
function userStatus(User $user = null)
{
    static $status;

    if (! $user) {
        return setting('statusdef');
    }

    if (is_null($status)) {
        saveStatus(3600);
        $status = unserialize(file_get_contents(STORAGE.'/temp/status.dat'));
    }
    return isset($status[$user->id]) ? $status[$user->id] : setting('statusdef');
}

// --------------- Функция кэширования настроек -------------------//
function saveSetting() {
    $setting = Setting::query()->pluck('value', 'name')->all();
    file_put_contents(STORAGE.'/temp/setting.dat', serialize($setting), LOCK_EX);
}

// ------------------ Функция вывода рейтинга --------------------//
function ratingVote($rating)
{

    $rating = round($rating / 0.5) * 0.5;

    $full_stars = floor($rating);
    $half_stars = ceil($rating - $full_stars);
    $empty_stars = 5 - $full_stars - $half_stars;

    $output = '<div class="star-rating fa-lg text-danger">';
    $output .= str_repeat('<i class="fa fa-star"></i>', $full_stars);
    $output .= str_repeat('<i class="fa fa-star-half-o"></i>', $half_stars);
    $output .= str_repeat('<i class="fa fa-star-o"></i>', $empty_stars);
    $output .= '</div>';

    return $output;
}

// --------------- Функция листинга всех файлов и папок ---------------//
function scanFiles($dirname)
{
    global $arr;

    if (empty($arr['files'])) {
        $arr['files'] = [];
    }
    if (empty($arr['totalfiles'])) {
        $arr['totalfiles'] = 0;
    }
    if (empty($arr['totaldirs'])) {
        $arr['totaldirs'] = 0;
    }

    $files = preg_grep('/^([^.])/', scandir($dirname));

    foreach ($files as $file) {
        if (is_file($dirname.'/'.$file)) {
            $ext = getExtension($file);

            if (!in_array($ext, explode(',',setting('nocheck')) )) {
                $arr['files'][] = $dirname.'/'.$file.' - '.dateFixed(filemtime($dirname.'/'.$file), 'j.m.Y / H:i').' - '.formatFileSize($dirname.'/'.$file);
                $arr['totalfiles']++;
            }
        }

        if (is_dir($dirname.'/'.$file)) {
            $arr['files'][] = $dirname.'/'.$file;
            $arr['totaldirs']++;
            scanFiles($dirname.'/'.$file);
        }
    }

    return $arr;
}

/**
 * Формирует календарь
 *
 * @param  int   $month
 * @param  int   $year
 * @return array
 */
function makeCalendar($month, $year)
{
    $wday = date("w", mktime(0, 0, 0, $month, 1, $year));

    if ($wday == 0) {
        $wday = 7;
    }

    $n = - ($wday-2);
    $cal = [];
    for ($y = 0; $y < 6; $y++) {
        $row = [];
        $notEmpty = false;
        for ($x = 0; $x < 7; $x++, $n++) {
            if (checkdate($month, $n, $year)) {
                $row[] = $n;
                $notEmpty = true;
            } else {
                $row[] = "";
            }
        }
        if (!$notEmpty) break;
        $cal[] = $row;
    }
    return $cal;
}

// --------------- Функция сохранения количества денег  у юзера ---------------//
function saveUserMoney($time = 0)
{
    if (empty($time) || @filemtime(STORAGE."/temp/money.dat") < time() - $time) {

        $users = User::query()
            ->where('money', '>', 0)
            ->pluck('money', 'id')
            ->all();

        file_put_contents(STORAGE."/temp/money.dat", serialize($users), LOCK_EX);
    }
}

// --------------- Функция подсчета денег у юзера ---------------//
function userMoney(User $user)
{
    static $userMoneys;

    if (empty($userMoneys)) {
        saveUserMoney(3600);
        $userMoneys = unserialize(file_get_contents(STORAGE."/temp/money.dat"));
    }

    return $userMoneys[$user->id] ?? 0;
}

// --------------- Функция сохранения количества писем ---------------//
function saveUserMail($time = 0)
{
    if (empty($time) || @filemtime(STORAGE."/temp/usermail.dat") < time() - $time) {

        $messages = Inbox::query()
            ->select('user_id', DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id')
            ->all();

        file_put_contents(STORAGE."/temp/usermail.dat", serialize($messages), LOCK_EX);
    }
}

// --------------- Функция подсчета писем у юзера ---------------//
function userMail(User $user)
{
    saveUserMail(3600);
    $userMails = unserialize(file_get_contents(STORAGE."/temp/usermail.dat"));
    return $userMails[$user->id] ?? 0;
}

/**
 * Возвращает аватар для пользователя по умолчанию
 *
 * @param string  $login логин пользователя
 * @return string        код аватара
 */
function defaultAvatar($login)
{
    $color  = '#'.substr(dechex(crc32($login)), 0, 6);
    $letter = utfSubstr($login, 0, 1);

    return '<div class="avatar" style="background:'.$color.'"><a href="/user/'.$login.'">'.$letter.'</a></div>';
}

/**
 * Возвращает аватар пользователя
 *
 * @param  User|null $user объект пользователя
 * @return string          аватар пользователя
 */
function userAvatar(User $user = null)
{
    if (! $user) {
        return '<img src="/assets/img/images/avatar_guest.png" alt=""> ';
    }

    if ($user->avatar && file_exists(HOME.'/uploads/avatars/'.$user->avatar)) {
        return '<a href="/user/'.$user->login.'"><img src="/uploads/avatars/'.$user->avatar.'" alt=""></a> ';
    }

    return defaultAvatar($user->login);
    //return '<a href="/user/'.$user->login.'"><img src="/assets/img/images/avatar_default.png" alt=""></a> ';
}


// --------------- Функция подсчета человек в контакт-листе ---------------//
function userContact(User $user)
{
    return Contact::query()->where('user_id', $user->id)->count();
}

// --------------- Функция подсчета человек в игнор-листе ---------------//
function userIgnore(User $user)
{
    return Ignore::query()->where('user_id', $user->id)->count();
}

// --------------- Функция подсчета записей на стене ---------------//
function userWall(User $user)
{
    return Wall::query()->where('user_id', $user->id)->count();
}

// ------------------ Функция подсчета пользователей онлайн -----------------//
function statsOnline($cache = 30)
{
    if (@filemtime(STORAGE."/temp/online.dat") < time()-$cache) {

        $online[0] = Online::query()->whereNotNull('user_id')->count();
        $online[1] = Online::query()->count();

        include_once(APP.'/Includes/count.php');

        file_put_contents(STORAGE."/temp/online.dat", serialize($online), LOCK_EX);
    }

    return unserialize(file_get_contents(STORAGE."/temp/online.dat"));
}

// ------------------ Функция вывода пользователей онлайн -----------------//
function showOnline()
{
    if (setting('onlines') == 1) {
        $online = statsOnline();
        return view('app/_online', compact('online'));
    }
}

// ------------------ Функция подсчета посещений -----------------//
function statsCounter()
{
    if (@filemtime(STORAGE."/temp/counter.dat") < time()-10) {
        $counts = Counter::query()->count();
        file_put_contents(STORAGE."/temp/counter.dat", serialize($counts), LOCK_EX);
    }

    return unserialize(file_get_contents(STORAGE."/temp/counter.dat"));
}

/**
 * Выводит счетчик посещений
 *
 * @return string
 */
function showCounter()
{
    if (isUser()) {
        //$visitPage =  setting('newtitle') ?? null;

        $visit = Visit::query()->firstOrNew(['user_id' => getUserId()]);

        $visit->fill([
            'self'       => server('PHP_SELF'),
            'page'       => null, //$visitPage,
            'ip'         => getClientIp(),
            'count'      => $visit->exists ? DB::raw('count + 1') : 0,
            'updated_at' => SITETIME,
        ])->save();
    }

    include_once (APP."/Includes/counters.php");

    if (setting('incount') > 0) {
        $count = statsCounter();

        return view('app/_counter', compact('count'));
    }
}

// --------------- Функция вывода количества зарегистрированных ---------------//
function statsUsers()
{
    if (@filemtime(STORAGE."/temp/statusers.dat") < time()-3600) {

        $startMonth = mktime(0, 0, 0, dateFixed(SITETIME, "n"), 1);

        $total = User::query()->count();
        $new   = User::query()->where('joined', '>', $startMonth)->count();

        if ($new) {
            $stat = $total.'/+'.$new;
        } else {
            $stat = $total;
        }

        file_put_contents(STORAGE."/temp/statusers.dat", $stat, LOCK_EX);
    }

    return file_get_contents(STORAGE."/temp/statusers.dat");
}

// --------------- Функция вывода количества админов и модеров --------------------//
function statsAdmins()
{
    if (@filemtime(STORAGE."/temp/statadmins.dat") < time()-3600) {

        $total = User::query()->whereBetween('level', [101, 105])->count();

        file_put_contents(STORAGE."/temp/statadmins.dat", $total, LOCK_EX);
    }

    return file_get_contents(STORAGE."/temp/statadmins.dat");
}

// --------------- Функция вывода количества жалоб --------------------//
function statsSpam()
{
    return Spam::query()->count();
}
// --------------- Функция вывода количества забаненных --------------------//
function statsBanned()
{
    return User::query()->where('ban', 1)->where('timeban', '>', SITETIME)->count();
}

// --------------- Функция вывода истории банов --------------------//
function statsBanHist()
{
    return Banhist::query()->count();
}

// ------------ Функция вывода количества ожидающих регистрации -----------//
function statsRegList()
{
    return User::query()->where('confirmreg', '>', 0)->count();
}

// --------------- Функция вывода количества забаненных IP --------------------//
function statsIpBanned()
{
    return Ban::query()->count();
}

// --------------- Функция вывода количества фотографий --------------------//
function statsGallery()
{
    if (@filemtime(STORAGE."/temp/statgallery.dat") < time()-900) {
        $total = Photo::query()->count();
        $totalnew = Photo::query()->where('created_at', '>', SITETIME-86400 * 3)->count();

        if (empty($totalnew)) {
            $stat = $total;
        } else {
            $stat = $total.'/+'.$totalnew;
        }

        file_put_contents(STORAGE."/temp/statgallery.dat", $stat, LOCK_EX);
    }

    return file_get_contents(STORAGE."/temp/statgallery.dat");
}

// --------------- Функция вывода количества новостей--------------------//
function statsNews()
{
    return News::query()->count();
}

// ---------- Функция вывода записей в черном списке ------------//
function statsBlacklist()
{
    $blacklist = BlackList::query()->select('type', DB::raw('count(*) as total'))
        ->groupBy('type')
        ->pluck('total', 'type')
        ->all();

    $list = $blacklist + array_fill(1, 3, 0);

    return $list[1].'/'.$list[2].'/'.$list[3];
}

// --------------- Функция вывода количества заголовков ----------------//
function statsAntimat()
{
    return Antimat::query()->count();
}

// --------------- Функция вывода количества смайлов ----------------//
function statsSmiles()
{
    return Smile::query()->count();
}

// ----------- Функция вывода даты последнего сканирования -------------//
function statsChecker()
{
    if (file_exists(STORAGE."/temp/checker.dat")) {
        return dateFixed(filemtime(STORAGE."/temp/checker.dat"), "j.m.y");
    } else {
        return 0;
    }
}

// --------------- Функция вывода количества приглашений --------------//
function statsInvite()
{
    $invited     = Invite::query()->where('used', 0)->count();
    $usedInvited = Invite::query()->where('used', 1)->count();

    return $invited.'/'.$usedInvited;
}

// --------------- Функция определение онлайн-статуса ---------------//
function userOnline($user)
{
    static $visits;

    $online = '<i class="fa fa-asterisk text-danger"></i>';

    if (is_null($visits)) {
        if (@filemtime(STORAGE."/temp/visit.dat") < time() - 10) {

            $visits = Visit::query()->select('user_id')
                ->where('updated_at', '>', SITETIME - 600)
                ->pluck('user_id', 'user_id')
                ->all();

            file_put_contents(STORAGE."/temp/visit.dat", serialize($visits), LOCK_EX);
        }

        $visits = unserialize(file_get_contents(STORAGE."/temp/visit.dat"));
    }

    if ($user && isset($visits[$user->id])) {
        $online = '<i class="fa fa-asterisk fa-spin text-success"></i>';
    }

    return $online;
}

// --------------- Функция определение пола пользователя ---------------//
function userGender($user)
{
    static $genders;

    $gender = 'male';

    if (is_null($genders)) {
        if (@filemtime(STORAGE."/temp/gender.dat") < time() - 600) {

            $genders = User::query()->select('id')
                ->where('gender', 2)
                ->pluck('id', 'id')
                ->all();

            file_put_contents(STORAGE."/temp/gender.dat", serialize($genders), LOCK_EX);
        }
        $genders = unserialize(file_get_contents(STORAGE."/temp/gender.dat"));
    }

    if ($user && isset($genders[$user->id])){
        $gender = 'female';
    }

    return '<i class="fa fa-'.$gender.' fa-lg"></i>';
}

// --------------- Функция вывода пользователей онлайн ---------------//
function allOnline()
{
    if (@filemtime(STORAGE."/temp/allonline.dat") < time()-30) {
        $visits = Visit::query()->select('user_id')
            ->where('updated_at', '>', SITETIME - 600)
            ->orderBy('updated_at', 'desc')
            ->pluck('user_id')
            ->all();

        file_put_contents(STORAGE."/temp/allonline.dat", serialize($visits), LOCK_EX);
    }

    return unserialize(file_get_contents(STORAGE."/temp/allonline.dat"));
}

// ------------------ Функция определение последнего посещения ----------------//
function userVisit($user)
{
    $state = '(Оффлайн)';

    if ( ! $user) {
        return $state;
    }

    $visit = Visit::query()->select('updated_at')->where('user_id', $user->id)->first();

    if ($visit) {
        if ($visit['updated_at'] > SITETIME - 600) {
            $state = '(Сейчас на сайте)';
        } else {
            $state = '(Последний визит: '.dateFixed($visit['updated_at']).')';
        }
    }

    return $state;
}

// --------------------- Функция вывода навигации в галерее ------------------------//
function photoNavigation($id)
{
    if (empty($id)) {
        return false;
    }

    $next = Photo::query()
        ->where('id', '>', $id)
        ->orderBy('id')
        ->pluck('id')
        ->first();

    $prev = Photo::query()
        ->where('id', '<', $id)
        ->orderBy('id', 'desc')
        ->pluck('id')
        ->first();

    return ['next' => $next, 'prev' =>$prev];
}

// --------------------- Функция вывода статистики блогов ------------------------//
function statsBlog()
{
    if (@filemtime(STORAGE."/temp/statblogblog.dat") < time()-900) {

        $totalblog = Blog::query()->count();
        $totalnew  = Blog::query()->where('created_at', '>', SITETIME - 86400 * 3)->count();

        if ($totalnew) {
            $stat = $totalblog.'/+'.$totalnew;
        } else {
            $stat = $totalblog;
        }

        file_put_contents(STORAGE."/temp/statblog.dat", $stat, LOCK_EX);
    }

    return file_get_contents(STORAGE."/temp/statblog.dat");
}

// --------------------- Функция вывода статистики форума ------------------------//
function statsForum()
{
    if (@filemtime(STORAGE."/temp/statforum.dat") < time()-600) {

        $topics = Topic::query()->count();
        $posts  = Post::query()->count();

        file_put_contents(STORAGE."/temp/statforum.dat", $topics.'/'.$posts, LOCK_EX);
    }

    return file_get_contents(STORAGE."/temp/statforum.dat");
}

// --------------------- Функция вывода статистики гостевой ------------------------//
function statsGuest()
{
    if (@filemtime(STORAGE."/temp/statguest.dat") < time()-600) {

        $total = Guest::query()->count();

        file_put_contents(STORAGE."/temp/statguest.dat", $total, LOCK_EX);
    }

    return file_get_contents(STORAGE."/temp/statguest.dat");
}

// -------------------- Функция вывода статистики админ-чата -----------------------//
function statsChat()
{
    return Chat::query()->count();
}

// ------------------ Функция вывода времени последнего сообщения --------------------//
function statsNewChat()
{
    return Chat::query()->max('created_at');
}

// --------------------- Функция вывода статистики загрузок ------------------------//
function statsLoad()
{
    if (@filemtime(STORAGE."/temp/statload.dat") < time() - 900) {

        $totalLoads = Cats::query()->sum('count');

        $totalNew = Down::query()->where('active', 1)
            ->where('created_at', '>', SITETIME - 86400 * 5)
            ->count();

        $stat = ($totalNew) ? $totalLoads.'/+'.$totalNew : $totalLoads;

        file_put_contents(STORAGE."/temp/statload.dat", $stat, LOCK_EX);
    }

    return file_get_contents(STORAGE."/temp/statload.dat");
}

// --------------------- Функция подсчета непроверенных файлов ------------------------//
function statsNewLoad()
{
    $totalNew = Down::query()->where('active', 0)
        ->count();

    $totalApprove = Down::query()->where('active', 0)
        ->where('approved', 1)
        ->count();

    return ($totalApprove) ? $totalNew.'/+'.$totalApprove : $totalNew;
}

// --------------------- Функция шифровки Email-адреса ------------------------//
function cryptMail($mail)
{
    $output = "";
    $strlen = strlen($mail);
    for ($i = 0; $i < $strlen; $i++) {
        $output .= '&#'.ord($mail[$i]).';';
    }
    return $output;
}

// ------------------- Функция обработки массива (int) --------------------//
function intar($string)
{
    if (empty($string)) return false;

    if (is_array($string)) {
        $newstring = array_map('intval', $string);
    } else {
        $newstring = [abs(intval($string))];
    }

    return $newstring;
}

// ------------------- Функция подсчета голосований --------------------//
function statVotes()
{
    if (@filemtime(STORAGE."/temp/statvote.dat") < time()-900) {

        $votes = Vote::query()
            ->select(DB::raw('count(*) AS cnt'), DB::raw('sum(count) AS sum'))
            ->where('closed', 0)
            ->first();

        file_put_contents(STORAGE."/temp/statvote.dat", $votes['cnt'].'/'.$votes['sum'], LOCK_EX);
    }

    return file_get_contents(STORAGE."/temp/statvote.dat");
}

// ------------------- Функция показа даты последней новости --------------------//
function statsNewsDate()
{
    if (@filemtime(STORAGE."/temp/statnews.dat") < time()-900) {
        $stat = 0;

        $news = News::query()->orderBy('created_at', 'desc')->first();

        if ($news) {
            $stat = dateFixed($news['created_at'], "d.m.y");
            if ($stat == 'Сегодня') {
                $stat = '<span style="color:#ff0000">Сегодня</span>';
            }
        }

        file_put_contents(STORAGE."/temp/statnews.dat", $stat, LOCK_EX);
    }

    return file_get_contents(STORAGE."/temp/statnews.dat");
}

// --------------------------- Функция вывода новостей -------------------------//
function lastNews()
{
    if (setting('lastnews') > 0) {

        $news = News::query()
            ->where('top', 1)
            ->orderBy('created_at', 'desc')
            ->limit(setting('lastnews'))
            ->get();

        $total = count($news);

        if ($total > 0) {
            foreach ($news as $data) {
                $data['text'] = str_replace('[cut]', '', $data['text']);
                echo '<i class="fa fa-circle-o fa-lg text-muted"></i> <a href="/news/'.$data['id'].'">'.$data['title'].'</a> ('.$data['comments'].') <i class="fa fa-caret-down news-title"></i><br>';

                echo '<div class="news-text" style="display: none;">'.bbCode($data['text']).'<br>';
                echo '<a href="/news/'.$data['id'].'/comments">Комментарии</a> ';
                echo '<a href="/news/'.$data['id'].'/end">&raquo;</a></div>';
            }
        }
    }
}

// ------------------------- Функция проверки авторизации  ------------------------//
function isUser()
{
    static $user = 0;

    if (! $user) {
        if (isset($_SESSION['id']) && isset($_SESSION['password'])) {

            $data = User::query()->find($_SESSION['id']);

            if ($data && $_SESSION['password'] == md5(env('APP_KEY').$data['password'])) {
                $user = $data;
            }
        }
    }

    return $user;
}

// ------------------------- Функция проверки администрации  ------------------------//
function isAdmin($access = [101, 102, 103, 105])
{
    if (isUser()) {
        if (in_array(user('level'), $access)) {
            return true;
        }
    }

    return false;
}

// ------------------ Функция вывода иконки расширения --------------------//
function icons($ext)
{
    switch ($ext) {
        case 'php':
            $ico = 'file-code-o';
            break;
        case 'ppt':
            $ico = 'file-powerpoint-o';
            break;
        case 'doc':
        case 'docx':
            $ico = 'file-word-o';
            break;
        case 'xls':
        case 'xlsx':
            $ico = 'file-excel-o';
            break;
        case 'txt':
        case 'css':
        case 'dat':
        case 'html':
        case 'htm':
            $ico = 'file-text-o';
            break;
        case 'wav':
        case 'amr':
        case 'mp3':
        case 'mid':
            $ico = 'file-audio-o';
            break;
        case 'zip':
        case 'rar':
        case '7z':
        case 'gz':
            $ico = 'file-archive-o';
            break;
        case '3gp':
        case 'mp4':
            $ico = 'file-video-o';
            break;
        case 'jpg':
        case 'jpeg':
        case 'bmp':
        case 'wbmp':
        case 'gif':
        case 'png':
            $ico = 'file-image-o';
            break;
        case 'ttf':
            $ico = 'font';
            break;
        case 'pdf':
            $ico = 'file-pdf-o';
            break;
        default: $ico = 'file-o';
    }
    return '<i class="fa fa-'.$ico.'"></i>';
}

// ------------------ Функция смешивания ассоциативного массива --------------------//
function shuffleAssoc(&$array)
{
    $keys = array_keys($array);

    shuffle($keys);
    $new = [];

    foreach($keys as $key) {
        $new[$key] = $array[$key];
    }

    $array = $new;
    return true;
}

// --------------- Функция обрезки слов -------------------//
function stripString($str, $words = 20) {
    return implode(' ', array_slice(explode(' ', strip_tags($str)), 0, $words));
}

// ------------------ Функция вывода пользовательской рекламы --------------------//
function getAdvertUser()
{
    if (!empty(setting('rekusershow'))) {
        if (@filemtime(STORAGE."/temp/rekuser.dat") < time() - 1800) {
            saveAdvertUser();
        }

        $datafile = unserialize(file_get_contents(STORAGE."/temp/rekuser.dat"));
        $total = count($datafile);

        if ($total > 0) {

            $rekusershow = (setting('rekusershow') > $total) ? $total : setting('rekusershow');

            $quot_rand = array_rand($datafile, $rekusershow);

            if ($rekusershow > 1) {
                $result = [];
                for($i = 0; $i < $rekusershow; $i++) {
                    $result[] = $datafile[$quot_rand[$i]];
                }
                $result = implode('<br>', $result);
            } else {
                $result = $datafile[$quot_rand];
            }

            return view('advert/_user', compact('result'));
        }
    }
}

// --------------- Функция кэширования пользовательской рекламы -------------------//
function saveAdvertUser()
{
    $data = RekUser::query()->where('created_at', '>', SITETIME)->get();

    $links = [];

    if ($data->isNotEmpty()) {
        foreach ($data as $val) {
            if ($val['color']) {
                $val['name'] = '<span style="color:'.$val['color'].'">'.$val['name'].'</span>';
            }

            $link = '<a href="'.$val['site'].'" target="_blank" rel="nofollow">'.$val['name'].'</a>';

            if ($val['bold']) {
                $link = '<b>'.$link.'</b>';
            }

            $links[] = $link;
        }
    }

    file_put_contents(STORAGE."/temp/rekuser.dat", serialize($links), LOCK_EX);
}

// --------------------------- Функция показа фотографий ---------------------------//
function recentPhotos($show = 5)
{
    if (@filemtime(STORAGE."/temp/recentphotos.dat") < time()-1800) {

        $recent = Photo::query()->orderBy('created_at', 'desc')->limit($show)->get();

        file_put_contents(STORAGE."/temp/recentphotos.dat", serialize($recent), LOCK_EX);
    }

    $photos = unserialize(file_get_contents(STORAGE."/temp/recentphotos.dat"));

    if ($photos->isNotEmpty()) {
        foreach ($photos as $data) {
            echo '<a href="/gallery/'.$data['id'].'">'.resizeImage('uploads/pictures/', $data['link'], setting('previewsize'), ['alt' => $data['title'], 'class' => 'rounded', 'style' => 'width: 100px; height: 100px;']).'</a>';
        }

        echo '<br>';
    }
}

// --------------- Функция кэширования последних тем форума -------------------//
function recentTopics($show = 5)
{
    if (@filemtime(STORAGE."/temp/recenttopics.dat") < time()-180) {
        $topics = Topic::query()->orderBy('updated_at', 'desc')->limit($show)->get();
        file_put_contents(STORAGE."/temp/recenttopics.dat", serialize($topics), LOCK_EX);
    }

    $topics = unserialize(file_get_contents(STORAGE."/temp/recenttopics.dat"));

    if ($topics->isNotEmpty()) {
        foreach ($topics as $topic) {
            echo '<i class="fa fa-circle-o fa-lg text-muted"></i>  <a href="/topic/'.$topic['id'].'">'.$topic['title'].'</a> ('.$topic->posts.')';
            echo '<a href="/topic/'.$topic['id'].'/end">&raquo;</a><br>';
        }
    }
}

// ------------- Функция кэширования последних файлов в загрузках -----------------//
function recentFiles($show = 5)
{
    if (@filemtime(STORAGE."/temp/recentfiles.dat") < time()-600) {

        $files = Down::query()
            ->where('active', 1)
            ->orderBy('created_at', 'desc')
            ->limit($show)
            ->get();

        file_put_contents(STORAGE."/temp/recentfiles.dat", serialize($files), LOCK_EX);
    }

    $files = unserialize(file_get_contents(STORAGE."/temp/recentfiles.dat"));

    if ($files->isNotEmpty()) {
        foreach ($files as $file){

            $filesize = $file['link'] ? formatFileSize(HOME.'/uploads/files/'.$file['link']) : 0;
            echo '<i class="fa fa-circle-o fa-lg text-muted"></i>  <a href="/load/down?act=view&amp;id='.$file->id.'">'.$file->title.'</a> ('.$filesize.')<br>';
        }
    }
}

// ------------- Функция кэширования последних статей в блогах -----------------//
function recentBlogs($show = 5)
{
    if (@filemtime(STORAGE."/temp/recentblog.dat") < time()-600) {
        $blogs = Blog::query()
            ->orderBy('created_at', 'desc')
            ->limit($show)
            ->get();

        file_put_contents(STORAGE."/temp/recentblog.dat", serialize($blogs), LOCK_EX);
    }

    $blogs = unserialize(file_get_contents(STORAGE."/temp/recentblog.dat"));

    if ($blogs->isNotEmpty()) {
        foreach ($blogs as $blog) {
            echo '<i class="fa fa-circle-o fa-lg text-muted"></i> <a href="/article/'.$blog->id.'">'.$blog->title.'</a> ('.$blog->comments.')<br>';
        }
    }
}

// ------------- Функция вывода количества предложений и пожеланий -------------//
function statsOffers()
{
    if (@filemtime(STORAGE."/temp/offers.dat") < time()-10800) {

        $offers   = Offer::query()->where('type', 0)->count();
        $problems = Offer::query()->where('type', 1)->count();

        file_put_contents(STORAGE."/temp/offers.dat", $offers.'/'.$problems, LOCK_EX);
    }

    return file_get_contents(STORAGE."/temp/offers.dat");
}

// ------------------------- Функция открытия файла ------------------------//
function fn_open($name, $mode, $method, $level) {
    if ($method == 2) {
        $name = "{$name}.bz2";
        return bzopen($name, $mode);
    } elseif ($method == 1) {
        $name = "{$name}.gz";
        return gzopen($name, "{$mode}b{$level}");
    } else {
        return fopen($name, "{$mode}b");
    }
}

// ------------------------- Функция записи в файл ------------------------//
function fn_write($fp, $str, $method) {
    if ($method == 2) {
        bzwrite($fp, $str);
    } elseif ($method == 1) {
        gzwrite($fp, $str);
    } else {
        fwrite($fp, $str);
    }
}

// ------------------------- Функция закрытия файла ------------------------//
function fn_close($fp, $method) {
    if ($method == 2) {
        bzclose($fp);
    } elseif ($method == 1) {
        gzclose($fp);
    } else {
        fflush($fp);
        fclose($fp);
    }
}

// ------------------ Функция пересчета сообщений и комментарий ---------------//
/**
 * Пересчитывает счетчики
 *
 * @param  string $mode сервис счетчиков
 * @return void
 */
function restatement($mode)
{
    switch ($mode) {
        case 'forum':
            DB::update('update topics set posts = (select count(*) from posts where topics.id = posts.topic_id)');
            DB::update('update forums set topics = (select count(*) from topics where forums.id = topics.forum_id)');
            DB::update('update forums set posts = (select SUM(posts) from topics where forums.id = topics.forum_id)');
            break;

        case 'blog':
            DB::update('update catsblog set count = (select count(*) from blogs where catsblog.id = blogs.category_id)');
            DB::update('update blogs set comments = (select count(*) from comments where relate_type = "'.Blog::class.'" and blogs.id = comments.relate_id)');
            break;

        case 'load':
            DB::update('update cats set count = (select count(*) from downs where cats.id = downs.category_id and active = ?)', [1]);
            DB::update('update downs set comments = (select count(*) from comments where relate_type = "'.Down::class.'" and downs.id = comments.relate_id)');
            break;

        case 'news':
            DB::update('update news set comments = (select count(*) from comments where relate_type = "'.News::class.'" and news.id = comments.relate_id)');
            break;

        case 'photo':
            DB::update('update photo set comments = (select count(*) from comments where relate_type=  "'.Photo::class.'" and photo.id = comments.relate_id)');
            break;
    }
}

// ------------------------ Функция записи в файл ------------------------//
function writeFiles($filename, $text, $clear = 0, $chmod = 0)
{

    if (empty($clear)) {
        file_put_contents($filename, $text, FILE_APPEND | LOCK_EX);
    } else {
        file_put_contents($filename, $text, LOCK_EX);
    }

    if (!empty($chmod)) {
        @chmod($filename, $chmod);
    }
}

// ------------------- Функция подсчета строк в файле--------------------//
function counterString($files)
{
    $count_lines = 0;
    if (file_exists($files)) {
        $lines = file($files);
        $count_lines = count($lines);
    }
    return $count_lines;
}

// ------------- Функция кэширования админских ссылок -------------//
function cacheAdminLinks($cache=10800)
{
    if (@filemtime(STORAGE.'/temp/adminlinks.dat') < time()-$cache) {
        $files = array_diff(scandir(APP.'/modules/admin/links'), ['.', '..']);
        $links = [];

        foreach ($files as $file){
            $access = intval(preg_replace('/[^\d]+/', '', $file));
            $links[$access][] = $file;
        }
        file_put_contents(STORAGE.'/temp/adminlinks.dat', serialize($links), LOCK_EX);
    }

    return unserialize(file_get_contents(STORAGE.'/temp/adminlinks.dat'));
}

// ------------- Функция вывода админских ссылок -------------//
function showAdminLinks($level = 0)
{
    $links = cacheAdminLinks();

    if (!empty($links[$level])){
        foreach ($links[$level] as $link){
            if (file_exists(APP.'/modules/admin/links/'.$link)){
                include_once(APP.'/modules/admin/links/'.$link);
            }
        }
    }
}

// ------------- Функция кэширования уменьшенных изображений -------------//
function resizeImage($dir, $name, $size, $params = [])
{
    if (!empty($name) && file_exists(HOME.'/'.$dir.$name)){

        $prename = str_replace('/', '_', $dir.$name);
        $newname = substr($prename, 0, strrpos($prename, '.'));
        $imgsize = getimagesize(HOME.'/'.$dir.$name);

        if (empty($params['alt'])) $params['alt'] = $name;

        if (! isset($params['class'])) {
            $params['class'] = 'img-fluid';
        }

        $strParams = [];
        foreach ($params as $key => $param) {
            $strParams[] = $key.'="'.$param.'"';
        }

        $strParams = implode(' ', $strParams);

        if ($imgsize[0] <= $size && $imgsize[1] <= $size) {
            return '<img src="/'.$dir.$name.'"'.$strParams.'>';
        }

        if (!file_exists(HOME.'/uploads/thumbnail/'.$prename) || filesize(HOME.'/uploads/thumbnail/'.$prename) < 18) {
            $handle = new upload(HOME.'/'.$dir.$name);

            if ($handle -> uploaded) {
                $handle -> file_new_name_body = $newname;
                $handle -> image_resize = true;
                $handle -> image_ratio = true;
                $handle -> image_ratio_no_zoom_in = true;
                $handle -> image_y = $size;
                $handle -> image_x = $size;
                $handle -> file_overwrite = true;
                $handle -> process(HOME.'/uploads/thumbnail/');
            }
        }
        return '<img src="/uploads/thumbnail/'.$prename.'"'.$strParams.'>';
    }

    return '<img src="/assets/img/images/photo.jpg" alt="nophoto">';
}

// ------------- Функция вывода ссылки на анкету -------------//
function profile($user, $color = false)
{
    if ($user){
        if ($color){
            return '<a href="/user/'.$user->login.'"><span style="color:'.$color.'">'.$user->login.'</span></a>';
        } else {
            return '<a href="/user/'.$user->login.'">'.$user->login.'</a>';
        }
    }
    return setting('guestsuser');
}

/**
 * Форматирует вывод числа
 *
 * @param integer $num
 * @return string
 */
function formatNum($num)
{
    if ($num > 0) {
        return '<span style="color:#00aa00">+'.$num.'</span>';
    } elseif ($num < 0) {
        return '<span style="color:#ff0000">'.$num.'</span>';
    } else {
        return '<span>0</span>';
   }
}

// ------------- Добавление пользовательского файла в ZIP-архив -------------//
function copyrightArchive($filename)
{

    $readme_file = HOME.'/assets/Visavi_Readme.txt';
    $ext = getExtension($filename);

    if ($ext == 'zip' && file_exists($readme_file)){
        $archive = new PclZip($filename);
        $archive->add($readme_file, PCLZIP_OPT_REMOVE_PATH, dirname($readme_file));

        return true;
    }
}

// ------------- Функция загрузки и обработки изображений -------------//
function uploadImage($file, $weight, $size, $newName = false)
{
    $handle = new FileUpload($file);

    if ($handle->uploaded) {
        $handle -> image_resize = true;
        $handle -> image_ratio = true;
        $handle -> image_ratio_no_zoom_in = true;
        $handle -> image_y = setting('screensize');
        $handle -> image_x = setting('screensize');
        $handle -> file_overwrite = true;

        if ($handle->file_src_name_ext == 'png' ||
            $handle->file_src_name_ext == 'bmp') {
            $handle->image_convert = 'jpg';
        }

        if ($newName) {
            $handle -> file_new_name_body = $newName;
        }

        if (setting('copyfoto')) {
            $handle -> image_watermark = HOME.'/assets/img/images/watermark.png';
            $handle -> image_watermark_position = 'BR';
        }

        $handle -> ext_check = ['jpg', 'jpeg', 'gif', 'png', 'bmp'];
        $handle -> file_max_size = $weight;  // byte
        $handle -> image_max_width = $size;  // px
        $handle -> image_max_height = $size; // px
        $handle -> image_min_width = 100;     // px
        $handle -> image_min_height = 100;    // px

        return $handle;
    }

    return false;
}

// ----- Функция определения входит ли пользователь в контакты -----//
function isContact($user, $contactUser)
{
    $isContact = Contact::query()
        ->where('user_id', $user->id)
        ->where('contact_id', $contactUser->id)
        ->first();

    if ($isContact) {
        return true;
    }

    return false;
}

// ----- Функция определения входит ли пользователь в игнор -----//
function isIgnore($user, $ignoreUser)
{

    $isIgnore = Ignore::query()
        ->where('user_id', $user->id)
        ->where('ignore_id', $ignoreUser->id)
        ->first();

    if ($isIgnore) {
        return true;
    }

    return false;
}

// ----- Функция рекурсивного удаления директории -----//
function removeDir($dir)
{
    if (file_exists($dir)){
        if ($files = glob($dir.'/*')) {
            foreach($files as $file) {
                is_dir($file) ? removeDir($file) : unlink($file);
            }
        }
        rmdir($dir);
    }
}

// ----- Функция отправки приватного сообщения -----//
function sendPrivate($userId, $authorId, $text, $time = SITETIME)
{
    if ($user = User::find($userId)) {

        Inbox::query()->create([
            'user_id'    => $userId,
            'author_id'  => $authorId,
            'text'       => $text,
            'created_at' => $time,
        ]);

        $user->increment('newprivat');
        saveUserMail();

        return true;
    }

    return false;
}

// ----- Функция подготовки приватного сообщения -----//
function textPrivate($id, $replace = [])
{
    $message = Notice::query()->find($id);

    if (! $message) {
        return 'Отсутствует текст сообщения!';
    }

    foreach ($replace as $key => $val){
        $message->text = str_replace($key, $val, $message->text);
    }

    return $message->text;
}

// ------------ Функция статистики производительности -----------//
function performance()
{
    if (isAdmin() && setting('performance')){

        $queries = env('APP_DEBUG') ? getQueryLog() : [];

        return view('app/_performance', compact('queries'));
    }
}

/**
 * Очистка кеш-файлов
 * @return boolean результат выполнения
 */
function clearCache()
{
    $cachefiles = glob(STORAGE.'/temp/*.dat');
    $cachefiles = array_diff($cachefiles, [
        STORAGE.'/temp/checker.dat',
        STORAGE.'/temp/counter7.dat'
    ]);

    if (is_array($cachefiles) && count($cachefiles)>0){
        foreach ($cachefiles as $file) {
            unlink ($file);
        }
    }

    // Авто-кэширование данных
    ipBan(true);

    return true;
}

/**
 * Возвращает текущую страницу
 *
 * @param null $url
 * @return string текущая страница
 */
function returnUrl($url = null)
{
    if (Request::is('/', 'login', 'register', 'recovery', 'ban', 'closed')) {
        return false;
    }
    $query = Request::has('return') ? Request::input('return') : Request::path();
    return '?return='.urlencode(is_null($url) ? $query : $url);
}

/**
 * Возвращает подключенный шаблон
 *
 * @param $template
 * @param  array $params массив параметров
 * @param  boolean $return выводить или возвращать код
 * @return string сформированный код
 */
function view($template, $params = [], $return = false)
{
    $blade = new Blade([RESOURCES.'/views', HOME.'/themes'], STORAGE.'/cache');

    if ($return) {
        return $blade->render($template, $params);
    } else {
        echo $blade->render($template, $params);
    }
}

/**
 * Сохраняет страницы с ошибками
 *
 * @param  integer $code    код ошибки
 * @param  string  $message текст ошибки
 * @return string  сформированная страница с ошибкой
 */
function abort($code, $message = null)
{
    if ($code == 403) {
        header($_SERVER['SERVER_PROTOCOL'].' 403 Forbidden');
    }

    if ($code == 404) {
        header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
    }

    if (setting('errorlog') && in_array($code, [403, 404])) {

        $error = new Log();
        $error->code = $code;
        $error->request = utfSubstr(server('REQUEST_URI'), 0, 200);
        $error->referer = utfSubstr(server('HTTP_REFERER'), 0, 200);
        $error->user_id = getUserId();
        $error->ip = getClientIp();
        $error->brow = getUserAgent();
        $error->created_at = SITETIME;
        $error->save();

        Log::query()
            ->where('code', $code)
            ->where('created_at', '<', SITETIME - 3600 * 24 * setting('maxlogdat'))
            ->delete();
    }

    if (Request::ajax()) {
        header($_SERVER['SERVER_PROTOCOL'].' 200 OK');

        json_encode([
            'status' => 'error',
            'message' => $message,
        ]);
    } else {
        $referer = Request::header('referer') ?? null;
        view('errors/'.$code, compact('message', 'referer'));
    }

    exit();
}

/**
 * Переадресовывает пользователя
 *
 * @param  string  $url адрес переадресации
 * @param  boolean $permanent постоянное перенаправление
 * @return void
 */
function redirect($url, $permanent = false)
{
    if (isset($_SESSION['captcha'])) {
        $_SESSION['captcha'] = null;
    }

    if ($permanent){
        header($_SERVER['SERVER_PROTOCOL'].' 301 Moved Permanently');
    }

    header('Location: '.$url);
    exit();
}

/**
 * Сохраняет flash уведомления
 *
 * @param string $status статус уведомления
 * @param mixed $message массив или текст с уведомлениями
 * @return void
 */
function setFlash($status, $message)
{
    $_SESSION['flash'][$status] = $message;
}

/**
 * Возвращает ошибку
 *
 * @param  mixed $errors ошибки
 * @return string сформированный блок с ошибкой
 */
function showError($errors)
{
    if (is_array($errors)) {
        $errors = implode('<br><i class="fa fa-exclamation-circle fa-lg text-danger"></i> ', $errors);
    }

    return view('app/_error', compact('errors'));
}

/**
 * Сохраняет POST данные введенных пользователем
 *
 * @param array $data массив полей
 */
function setInput(array $data)
{
    $prepareData = [];
    foreach($data as $key => $value) {

        if (is_object($value)) {
            continue;
        }

        $prepareData[$key] = $value;
    }

    $_SESSION['input'] = $prepareData;
}

/**
 * Возвращает значение из POST данных
 *
 * @param string $name имя поля
 * @param string $default
 * @return string сохраненный текст
 */
function getInput($name, $default = null)
{
    if (isset($_SESSION['input'][$name])) {
        $input = $_SESSION['input'][$name];
        unset($_SESSION['input'][$name]);
    }

    return $input ?? $default;
}

/**
 * Подсвечивает блок с полем для ввода сообщения
 *
 * @param string $field имя поля
 * @return string CSS класс ошибки
 */
function hasError($field)
{
    return isset($_SESSION['flash']['danger'][$field]) ? ' has-error' : '';
}

/**
 * Возвращает блок с текстом ошибки
 *
 * @param  string $field имя поля
 * @return string        блоки ошибки
 */
function textError($field)
{
    $text = null;

    if (isset($_SESSION['flash']['danger'][$field])) {
        $error = $_SESSION['flash']['danger'][$field];
        $text = '<div class="text-danger">'.$error.'</div>';
    }

    return $text;
}

/**
 * Проверяет является ли email валидным
 *
 * @param  string  $email адрес email
 * @return boolean результат проверки
 */
function isMail($email)
{
    return preg_match('#^([a-z0-9_\-\.])+\@([a-z0-9_\-\.])+(\.([a-z0-9])+)+$#', $email);
}

/**
 * Отправка уведомления на email
 *
 * @param  mixed   $to      Получатель
 * @param  string  $subject Тема письма
 * @param  string  $body    Текст сообщения
 * @param  array   $params  Дополнительные параметры
 * @return boolean          Результат отправки
 */
function sendMail($to, $subject, $body, $params = [])
{
    if (empty($params['from'])) {
        $params['from'] = [env('SITE_EMAIL') => env('SITE_ADMIN')];
    }

    $message = (new Swift_Message())
        ->setTo($to)
        ->setSubject($subject)
        ->setBody($body, 'text/html')
        ->setFrom($params['from'])
        ->setReturnPath(env('SITE_EMAIL'));

    if (isset($params['subscribe'])) {
        $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.env('SITE_EMAIL').'>, <'.setting('home').'/unsubscribe?key='.$params['subscribe'].'>');

        $body = str_replace('<!-- unsubscribe -->', '<br><br><small>Если вы не хотите получать эти email, пожалуйста, <a href="'.setting('home').'/unsubscribe?key='.$params['subscribe'].'">откажитесь от подписки</a></small>', $body);
        $message->setBody($body, 'text/html');
    }

    if (env('MAIL_DRIVER') == 'smtp') {
        $transport = (new Swift_SmtpTransport(env('MAIL_HOST'), env('MAIL_PORT'), env('MAIL_ENCRYPTION')))
            ->setUsername(env('MAIL_USERNAME'))
            ->setPassword(env('MAIL_PASSWORD'));
    } else {
        $transport = new Swift_SendmailTransport(env('MAIL_PATH'));
    }

    $mailer = new Swift_Mailer($transport);
    return $mailer->send($message);
}

/**
 * Возвращает форматированную дату
 *
 * @param string $format отформатированная дата
 * @param mixed  $date временная метки или дата
 * @return string отформатированная дата
 */
function dateFormat($format, $date = null)
{
    $date = (is_null($date)) ? SITETIME : strtotime($date);

    $eng = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    $rus = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
    return str_replace($eng, $rus, date($format, $date));
}

/**
 * Возвращает расширение файла
 *
 * @param  string $filename имя файла
 * @return string расширение
 */
function getExtension($filename)
{
    return pathinfo($filename, PATHINFO_EXTENSION);
}

/**
 * Возвращает размер файла
 *
 * @param  string  $filename путь к файлу
 * @param  integer $decimals кол. чисел после запятой
 * @return string            форматированный вывод размера
 */
function sizeFormat($filename, $decimals = 1)
{
    if (! file_exists($filename)) {
        return 0;
    }

    $bytes  = filesize($filename);
    $size   = ['B','kB','MB','GB','TB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    $unit   = $size[$factor] ?? '';
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)).$unit;
}

/**
 * Склоняет числа
 *
 * @param  integer $num  число
 * @param  mixed   $forms массив склоняемых слов (один, два, много)
 * @return string  форматированная строка
 */
function plural($num, $forms)
{
    if (! is_array($forms)) {
        $forms = explode(',', $forms);
    }

    if (count($forms) == 1) {
        return $num.' '.$forms[0];
    }

    if ($num % 100 > 10 &&  $num % 100 < 15) return $num.' '.$forms[2];
    if ($num % 10 == 1) return $num.' '.$forms[0];
    if ($num % 10 > 1 && $num %10 < 5) return $num.' '.$forms[1];
    return $num.' '.$forms[2];
}

/**
 * Валидирует даты
 *
 * @param  string $date   дата
 * @param  string $format формат даты
 * @return boolean        результат валидации
 */
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

/**
 * Обрабатывает BB-код
 *
 * @param  string  $text  Необработанный текст
 * @param  boolean $parse Обрабатывать или вырезать код
 * @return string         Обработанный текст
 */
function bbCode($text, $parse = true)
{
    $bbCode = new BBCode();

    if (! $parse) {
        return $bbCode->clear($text);
    }

    $text = $bbCode->parse($text);
    $text = $bbCode->parseSmiles($text);

    return $text;
}

/**
 * Определяет браузер
 *
 * @param string|null $userAgent
 * @return string браузер и версия браузера
 */
function getUserAgent($userAgent = null)
{
    $browser = new Browser();
    if ($userAgent) {
        $browser->setUserAgent($userAgent);
    }

    $brow = $browser->getBrowser();
    $version = implode('.', array_slice(explode('.', $browser->getVersion()), 0, 2));
    return mb_substr($version == 'unknown' ? $brow : $brow.' '.$version, 0, 25, 'utf-8');
}

/**
 * Определяет IP пользователя
 *
 * @return string IP пользователя
 */
function getClientIp()
{
    $ip = Request::ip();
    return $ip == '::1' ? '127.0.0.1' : $ip;
}

/**
 * Возвращает серверные переменные
 *
 * @param string|null $key     ключ массива
 * @param string|null $default значение по умолчанию
 * @return mixed               данные
 */
function server($key = null, $default = null)
{
    $server = Request::server($key, $default);
    if ($key == 'REQUEST_URI') $server = urldecode($server);
    if ($key == 'PHP_SELF') $server = current(explode('?', server('REQUEST_URI')));

    return check($server);
}

/**
 * Возвращает логин пользователя
 *
 * @return string
 */
function getUsername()
{
    return user('login') ? user('login') : setting('guestsuser');
}

/**
 * Возвращает ID пользователя
 *
 * @return int
 */
function getUserId()
{
    return isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
}

/**
 * Возвращает объект пользователя по логину
 *
 * @param  string    $login логин пользователя
 * @return User|null        объект пользователя
 */
function getUserByLogin($login)
{
    return User::query()->where('login', $login)->first();
}

/**
 * Возвращает объект пользователя по id
 *
 * @param  int       $id ID пользователя
 * @return User|null     объект пользователя
 */
function getUserById($id)
{
    return User::query()->find($id);
}

/**
 * Возвращает настройки пользователя по ключу
 *
 * @param  string $key ключ массива
 * @return string      данные
 */
function user($key = null)
{
    if (Registry::has('user')) {
        if (empty($key)) {
            return Registry::get('user');
        } else {
            return Registry::get('user')[$key] ?? null;
        }
    }

    return null;
}

/**
 * Генерирует постраничную навигация
 *
 * @param  array  $page массив данных
 * @return string       сформированный блок
 */
function pagination($page)
{
    if ($page['total'] > 0) {

        if (empty($page['crumbs'])) $page['crumbs'] = 3;

        $url = array_except($_GET, 'page');
        $request = $url ? '&'.http_build_query($url) : null;

        $pages = [];
        $pg_cnt = ceil($page['total'] / $page['limit']);
        $idx_fst = max($page['current'] - $page['crumbs'], 1);
        $idx_lst = min($page['current'] + $page['crumbs'], $pg_cnt);

        if ($page['current'] != 1) {
            $pages[] = [
                'page' => $page['current'] - 1,
                'title' => 'Предыдущая',
                'name' => '«',
            ];
        }

        if ($page['current'] > $page['crumbs'] + 1) {
            $pages[] = [
                'page' => 1,
                'title' => '1 страница',
                'name' => 1,
            ];
            if ($page['current'] != $page['crumbs'] + 2) {
                $pages[] = [
                    'separator' => true,
                    'name' => ' ... ',
                ];
            }
        }

        for ($i = $idx_fst; $i <= $idx_lst; $i++) {
            if ($i == $page['current']) {
                $pages[] = [
                    'current' => true,
                    'name' => $i,
                ];
            } else {
                $pages[] = [
                    'page' => $i,
                    'title' => $i.' страница',
                    'name' => $i,
                ];
            }
        }

        if ($page['current'] < $pg_cnt - $page['crumbs']) {
            if ($page['current'] != $pg_cnt - $page['crumbs'] - 1) {
                $pages[] = [
                    'separator' => true,
                    'name' => ' ... ',
                ];
            }
            $pages[] = [
                'page' => $pg_cnt,
                'title' => $pg_cnt . ' страница',
                'name' => $pg_cnt,
            ];
        }

        if ($page['current'] != $pg_cnt) {
            $pages[] = [
                'page' => $page['current'] + 1,
                'title' => 'Следующая',
                'name' => '»',
            ];
        }

        return view('app/_pagination', compact('pages', 'request'));
    }
}

/**
 * Обрабатывает постраничную навигацию
 *
 * @param  integer $limit элементов на страницу
 * @param  integer $total всего элементов
 * @return array          массив подготовленных данных
 */
function paginate($limit, $total)
{
    $current = Request::input('page');
    if ($current < 1) $current = 1;

    if ($total && $current * $limit >= $total) {
        $current = ceil($total / $limit);
    }

    $offset = intval(($current * $limit) - $limit);

    return compact('current', 'offset', 'limit', 'total');
}

/**
 * Устанавливает права доступа на папки
 *
 * @return void
 */
function install()
{
    $storage = glob(dirname(__DIR__).'/storage/*', GLOB_ONLYDIR);
    $uploads = glob(dirname(dirname(__DIR__)).'/public/uploads/*', GLOB_ONLYDIR);

    $dirs = array_merge($storage, $uploads);

    foreach ($dirs as $dir) {
        $old = umask(0);
        chmod ($dir, 0777);
        umask($old);
    }
}

/**
 * Возвращает сформированный код base64 картинки
 *
 * @param string  $path   путь к картинке
 * @param array   $params параметры
 * @return string         сформированный код
 */
function imageBase64($path, array $params = [])
{
    $type = pathinfo($path, PATHINFO_EXTENSION);
    $data = file_get_contents($path);

    if (! isset($params['class'])) {
        $params['class'] = 'img-fluid';
    }

    $strParams = [];
    foreach ($params as $key => $param) {
        $strParams[] = $key.'="'.$param.'"';
    }

    $strParams = implode(' ', $strParams);

    return '<img src="data:image/'.$type.';base64,'.base64_encode($data).'"'.$strParams.'>';
}

/**
 * Выводит прогресс-бар
 *
 * @param int  $percent
 * @param bool $title
 * @return string
 */
function progressBar($percent, $title = false)
{
    if (! $title){
        $title = $percent.'%';
    }

    return view('app/_progressbar', compact('percent', 'title'));
}

/**
 * Инициализирует языковую локализацию
 *
 * @param  string $locale
 * @param  string $fallback
 * @return \Illuminate\Translation\Translator
 */
function translator($locale = 'ru', $fallback = 'en')
{
    $translator = new \Illuminate\Translation\Translator(
        new \Illuminate\Translation\FileLoader(
            new \Illuminate\Filesystem\Filesystem(),
            RESOURCES.'/lang'
        ),
        $locale
    );
    $translator->setFallback($fallback);

    return $translator;
}

/**
 * Translate the given message.
 *
 * @param  string  $id
 * @param  array   $replace
 * @param  string  $locale
 * @return string
 */
function trans($id, $replace = [], $locale = null)
{
    return translator()->trans($id, $replace, $locale);
}

/**
 * Translates the given message based on a count.
 *
 * @param  string  $id
 * @param  int|array|\Countable  $number
 * @param  array   $replace
 * @param  string  $locale
 * @return string
 */
function trans_choice($id, $number, array $replace = [], $locale = null)
{
    return translator()->transChoice($id, $number, $replace, $locale);
}

/**
 * Возвращает форматированный список запросов
 * @return array
 */
function getQueryLog()
{
    $queries = DB::getQueryLog();
    $formattedQueries = [];
    foreach ($queries as $query) {
        $prep = $query['query'];
        foreach ($query['bindings'] as $binding) {
            $binding = is_int($binding) ? $binding : "'{$binding}'";
            $prep = preg_replace("#\?#", $binding, $prep, 1);
        }
        $formattedQueries[] = ['query' => $prep, 'time' => $query['time']];
    }
    return $formattedQueries;
}

/**
 * Выводит список забаненных ip
 *
 * @param  boolean $save нужно ли сбросить кеш
 * @return array         массив IP
 */
function ipBan($save = false)
{
    if (! $save && file_exists(STORAGE.'/temp/ipban.dat')) {
        $ipBan = unserialize(file_get_contents(STORAGE.'/temp/ipban.dat'));
    } else {
        $ipBan = Ban::query()->pluck('ip')->all();
        file_put_contents(STORAGE."/temp/ipban.dat", serialize($ipBan), LOCK_EX);
    }

    return $ipBan;
}

/**
 * Возвращает настройки сайта по ключу
 *
 * @param  string $key ключ массива
 * @return string      данные
 */
function setting($key = null)
{
    if (! Registry::has('setting')) {

        if (! file_exists(STORAGE.'/temp/setting.dat')) {
            $setting = Setting::query()->pluck('value', 'name')->all();
            file_put_contents(STORAGE.'/temp/setting.dat', serialize($setting), LOCK_EX);
        }
        $setting = unserialize(file_get_contents(STORAGE.'/temp/setting.dat'));

        Registry::set('setting', $setting);
    }

    if (empty($key)) {
        return Registry::get('setting');
    }

    return isset(Registry::get('setting')[$key]) ? Registry::get('setting')[$key] : null;
}

/**
 * Преобразует путь к сайту независимо от протокола
 *
 * @param  string $link адрес сайта
 * @return string       адрес сайта с протоколом
 */
function siteLink($link)
{
    return starts_with($link, '//') ? 'http:' . $link : $link;
}

/**
 * Возвращает имя сайта из ссылки
 *
 * @param  string $link ссылка на сайт
 * @return string       имя сайта
 */
function siteDomain($link)
{
    $link = strtolower($link);
    $link = str_replace(['http://www.', 'http://', 'https://', '//'], '', $link);
    $link = strtok($link, '/?');

    return $link;
}
