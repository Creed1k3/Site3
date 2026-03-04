<?php
// Version 19-11-22 18:00 (log to single file, CSV line)
const API_URL = "https://sendmelead.com/api/v3/lead/add";
const OFFER_ID = '55620611-1e97-4cdf-a260-be90a30a210f'; // ID выбранного оффера
const WEBMASTER_TOKEN = '25203159fc971234d5953ea0b6e06af8'; // Токен из Вашего профиля
const COST = 1990; // Цена на лендинге;
const NAME_FIELD = 'name';  // Как называется поле на ленде с именем/ФИО
const PHONE_FIELD = 'phone'; // Как называется поле на ленде с телефоном

// Куда редиректим если это не пост запрос с формой
$urlForNotPost = 'index.php';
// Куда редиректим если имя или телефон не заполнены
$urlForEmptyRequiredFields = 'index.php';
// Куда редиректим если сервер ответил что-то непонятное
$urlForNotJson = 'index.php';
// Куда редиректим если всё хорошо
$urlSuccess = 'success.php';

// ----------- ЛОГИРОВАНИЕ В ОДИН ФАЙЛ (CSV-строка) -----------
function writeToLog(array $data, $response)
{
    // формат: время и дата, имя, номер, clickid
    // пример: 2025-09-02 14:23:11,"Иван Иванов",+380501234567,abc123
    $dt = date('Y-m-d H:i:s');
    $name = isset($data['name']) ? str_replace('"', '""', $data['name']) : '';
    $phone = isset($data['phone']) ? $data['phone'] : '';
    $clickid = isset($data['clickid']) ? $data['clickid'] : '';

      $line = $dt . ',' . $name . ',' . $phone . ',' . $clickid . PHP_EOL;

    // один файл без ротации
    $logFile = __DIR__ . '/leads.log';
    // атомарная запись с блокировкой
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// ----------- Определение IP -----------
function getUserIP()
{
    // Get real visitor IP behind CloudFlare network
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    $client = @$_SERVER['HTTP_CLIENT_IP'];
    $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
    $remote = $_SERVER['REMOTE_ADDR'];

    if (filter_var($client, FILTER_VALIDATE_IP)) {
        $ip = $client;
    } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
        $ip = $forward;
    } else {
        $ip = $remote;
    }

    return $ip;
}

// ----------- Проверки -----------
$isCurlEnabled = function () {
    return function_exists('curl_version');
};

if (!$isCurlEnabled()) {
    echo "<pre>";
    echo "pls install curl\n";
    echo "For *unix open terminal and type this:\n";
    echo 'sudo apt-get install curl && apt-get install php-curl';
    die;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка: если поля не заполнены — уводим и ничего не отправляем
    if (empty($_POST[NAME_FIELD]) || empty($_POST[PHONE_FIELD])) {
        header('Location: ' . $urlForEmptyRequiredFields);
        exit;
    }

    $args = array(
        'name'   => $_POST[NAME_FIELD],
        'phone'  => $_POST[PHONE_FIELD],

        'offerId' => OFFER_ID,
        'domain'  => "http://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
        'ip'      => getUserIP(),
        'utm_campaign' => key_exists('utm_campaign', $_POST) ? $_POST['utm_campaign'] : null,
        'utm_content'  => key_exists('utm_content', $_POST) ? $_POST['utm_content'] : null,
        'utm_medium'   => key_exists('utm_medium', $_POST) ? $_POST['utm_medium'] : null,
        'utm_source'   => key_exists('utm_source', $_POST) ? $_POST['utm_source'] : null,
        'utm_term'     => key_exists('utm_term', $_POST) ? $_POST['utm_term'] : null,
        'clickid'      => key_exists('clickid', $_POST) ? $_POST['clickid'] : null,
        'pxl'        => key_exists('fbpxl', $_POST) ? $_POST['pxl'] : null,
        'cost'         => COST,
    );

    $data = json_encode($args);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'X-Token: ' . WEBMASTER_TOKEN,
        ),
    ));
    $result = curl_exec($curl);
    curl_close($curl);

    // логируем только краткую строку (без ответа сервера)
    writeToLog($args, $result);

    $result = json_decode($result, true);
    if ($result === null) {
        header('Location: ' . $urlForNotJson);
        exit;
    } else {
        $parameters = [
            'pxl' => $args['px'],
            'fio'   => $args['name'],
            'name'  => $args['name'],
            'phone' => $args['phone']
        ];
        $urlSuccess .= '?' . http_build_query($parameters);
        header('Location: ' . $urlSuccess);
        exit;
    }
} else {
    header('Location: ' . $urlForNotPost);
    exit;
}
?>
