<?php
$start = microtime(true);

if (isset($_POST['maxfriends'])) {

    function curlRequestPost($method, $params)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/' . $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, true); // указываем что будем использовать POST
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $output = curl_exec($ch) . PHP_EOL;

        if (curl_errno($ch)) {
            throw new \Exception('Error:' . curl_error($ch));
        }
        $data = json_decode($output);
        curl_close($ch);
        return $data;
    }

    function checkInt($data)
    {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    $maxFriends = !empty($_POST['maxfriends']) ? $_POST['maxfriends'] : 150;
    $maxFriends = checkInt($maxFriends);

    $minFriends = !empty($_POST['minfriends']) ? $_POST['minfriends'] : 29;
    $minFriends = checkInt($minFriends);

    $maxFollowers = !empty($_POST['maxfollowers']) ? $_POST['maxfollowers'] : 200;
    $maxFollowers = checkInt($maxFollowers);

    $minFollowers = !empty($_POST['minfollowers']) ? $_POST['minfollowers'] : 20;
    $minFollowers = checkInt($minFollowers);

    $ageFrom = !empty($_POST['agefrom']) ? $_POST['agefrom'] : 18;
    $ageFrom = checkInt($ageFrom);

    $ageTo = !empty($_POST['ageto']) ? $_POST['ageto'] : 30;
    $ageTo = checkInt($ageTo);

    if ($_POST['sex'] === 'Женский') {
        $sex = 1;
    } else {
        $sex = 2;
    }

    $groupsId = !empty($_POST['groupids']) ? $_POST['groupids'] : [57846937, 23148107];
    $groupsId = checkInt($groupsId);
    $groupsId = explode(',', $groupsId);
//    print_r($groupsId);die;

// Файл в который будем писать id пользователей
    $timestamp = time();
    $file = new \SplFileObject(__DIR__ . '/reports/' . $timestamp . '.csv', 'w');
    $file->setCsvControl(';');
    $file->fwrite(b"\xEF\xBB\xBF"); // передаем BOM последовательность, явно указывающую, что файл в кодировке UTF8

    $apiToken = '';
    $usersNumber = 1000; // число пользователей по которым будем искать, больше тысячи - нельзя
    $city = 1; // идентификатор города 1- Москва
    $sort = 0; // 1 — по дате регистрации, 0 — по популярности.
//    $universityId = 0;// идентификатор ВУЗа


// 1 Ищем пользователей

    $sumUsers = [];
// нахрен не нужон do while, т.к. все равно поиск тольк по первой тысяче, но мб когда пригодится
    foreach ($groupsId as $groupId) {
//        echo 'Group id: ' . $groupId . "</br>";
        $groupId = trim($groupId);

        $params = [];
        $users = [];
        $offset = 0;

        do {
            $params = [
                'access_token' => $apiToken,
                'q' => '', // строка поискового запроса. Например, Вася Бабич.
                'sort' => $sort,
                'offset' => $offset,
                'count' => 1000,
                'fields' => 'followers_count, has_mobile,last_seen, is_closed',
                'city' => $city,
                'country' => 1, //Россия
//        'university' => $universityId,
                'sex' => $sex,
                'status' => 1, //1 — не женат (не замужем);2 — встречается;3 — помолвлен(-а);4 — женат (замужем);5 — всё сложно;6 — в активном поиске;7 — влюблен(-а);8 — в гражданском браке
                'age_from' => $ageFrom,
                'age_to' => $ageTo,
                'online' => 0,// 1- пользователи онлайн, 0 - все
                'has_photo' => 1, // с фоткой 1 - да, 0 - нет
                'group_id' => $groupId,//идентификатор группы, среди пользователей которой необходимо проводить поиск.
                'v' => '5.89'
            ];
            $request = curlRequestPost('users.search', $params);
//            print_r($params);
//        print_r($request);
            $users = $request->response->items;
            $offset += 1000;
        } while ($offset < $usersNumber);
//        print_r($users);

        $oneMonth = new DateTime('now');
        $oneMonth->modify('-1 month');
        $oneMonth = $oneMonth->getTimestamp();// метка времени один месяц назад

        foreach ($users as $key => $user) {
//            print_r($user);
            // Предварительная сортировка пользователей
            if (isset($user->followers_count)) {
                if ($user->followers_count > $maxFollowers) {
                    continue;
                }
            }
            if ($user->has_mobile === 0) { // если не привязан мобильный, хз предполагается что у авторегов это так
//            echo 'не привязан мобильник' . PHP_EOL;
                continue;
            }
            if (isset($user->last_seen->time)) {
                if ($user->last_seen->time < $oneMonth) { // Если пользователь не заходил больше месяца
//                echo 'Человек давно не заходил' . PHP_EOL;
                    continue;
                }
            }
            if ($user->is_closed == 1) { // Если профиль закрыт
//            echo 'Профиль закрыт' . PHP_EOL;
                continue;
            }

            // Получаем список друзей пользователя
            $params = [
                'access_token' => $apiToken,
                'user_id' => $user->id, // Если параметр не задан, то считается, что он равен идентификатору текущего пользователя
                'count' => 5000,
                'v' => '5.8'
            ];
            $friends = curlRequestPost('friends.get', $params);
//    print_r($friends);

            $friendsNumber = 0;
            isset($friends->response->count) AND ($friendsNumber = $friends->response->count);
//            echo 'id: ' . $user->id . PHP_EOL;
//            echo 'Second name: ' . $user->last_name . PHP_EOL;
//            echo 'Number of friends: ' . $friendsNumber . PHP_EOL;
//            echo "</br>";

            // Получаем подписичников
            $params = [
                'access_token' => $apiToken,
                //    'user_id'=> $userId, // Если параметр не задан, то считается, что он равен идентификатору текущего пользователя
                'count' => 1000,
                'v' => '5.8'
            ];
            $followers = curlRequestPost('users.getFollowers', $params);
            //print_r($followers);

            $followersNumber = 0;
            isset($followers->response->count) AND ($followersNumber = $followers->response->count);
//echo $followersNumber . PHP_EOL;

// Итоговая проверка
            if ($friendsNumber > $minFriends and $friendsNumber < $maxFriends and $followersNumber < $maxFollowers) {
                $id = 'https://vk.com/id' . $user->id;
                $file->fputcsv([$id/*, $groupId*/]); // Можно писать группу, можно не писать
                $sumUsers[] = $id;
            }

//        echo $key . PHP_EOL;
        }

        unset($users);
        unset($params);
        unset($request);
        unset($offset);
//    exit;// Если надо по одной группе
    }
    $scriptDone = true;
//    echo 'Время выполнения скрипта: ' . round(microtime(true) - $start, 4) . ' сек.';
}
?>

<!doctype html>
<html lang="ru">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css"
          integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">

    <title>Zykunov VK Parser</title>
</head>
<body>

<?php
//print_r($_POST);
?>

<div class="container">
    <div class="row">
        <div class="col-1"></div>
        <div class="col-10 text-center bg-light rounded-sm">
            <p class="h3 mt-3">VK Parser</p>
            <?php if ($scriptDone) { ?>
                <details open="close">
                    <summary>Список</summary>
                    <spаn>
                        <?php
                        foreach ($sumUsers as $singleUser) {
                            echo "<a href='" . $singleUser . "' target=\"_blank\">" . $singleUser . "</a></br>";
                        }
                        ?>
                    </spаn>
                    </details>
                <p class="mt-4"><a href='/vkparser/reports/<?php echo $timestamp ?>.csv'>Сохранить список как файл</a></p>
            <?php } ?>

            <form class="mt-2 text-left" action="index.php" method="POST">
                <label class="mt-2">Пол</label>
                <select class="custom-select custom-select-lg mb-3" name="sex">
                    <option selected>Женский</option>
                    <option value="1">Мужской</option>
                </select>
                <div class="row">
                    <div class="col-6">
                        <label class="mt-2">Максимальное количество друзей</label>
                        <input class="form-control" name="maxfriends" pattern="[0-9]{1,4}" placeholder="150">
                    </div>
                    <div class="col-6">
                        <label class="mt-2">Минимальное количество друзей</label>
                        <input class="form-control" name="minfriends" pattern="[0-9]{1,4}" placeholder="29">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <label class="mt-2">Максимальное количество подписчиков</label>
                        <input class="form-control" name="maxfollowers" pattern="[0-9]{1,4}" placeholder="200">
                    </div>
                    <div class="col-6">
                        <label class="mt-2">Минимальное количество подписчиков</label>
                        <input class="form-control" name="minfollowers" pattern="[0-9]{1,4}" placeholder="20">
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <label class="mt-2">Возраст от:</label>
                        <input class="form-control" name="agefrom" pattern="[0-9]{1,3}" placeholder="18">
                    </div>
                    <div class="col-6">
                        <label class="mt-2">Возраст до:</label>
                        <input class="form-control" name="ageto" pattern="[0-9]{1,3}" placeholder="30">
                    </div>
                </div>

                <!--                <label class="mt-2">Идентификатор ВУЗа</label>-->
                <!--                <input class="form-control" name="institute">-->

                <label class="mt-2">Идентификатор города</label>
                <input class="form-control" name="city" placeholder="1 - Москва">

                <label class="mt-2">Идентификаторы групп, пабликов (через запятую)*</label>
                <input class="form-control" name="groupids" placeholder="57846937, 23148107">
                <small id="emailHelp" class="form-text text-muted mb-3"><a href="http://regvk.com/id" target="_blank">Узнать
                        id группы можно тут</a></small>


                <button type="submit" class="btn btn-primary btn-lg btn-block text-center mt-3 mb-3">Найти</button>
            </form>
            <small id="emailHelp" class="form-text text-muted mb-3">Если не указан какой-то параметр, будет подставлено
                значение по умолчанию
            </small>
        </div>
        <div class="col-1"></div>
    </div>
</div>


<!-- Optional JavaScript -->
<!-- jQuery first, then Popper.js, then Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.4.1.slim.min.js"
        integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n"
        crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"
        integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6"
        crossorigin="anonymous"></script>
</body>
</html>


