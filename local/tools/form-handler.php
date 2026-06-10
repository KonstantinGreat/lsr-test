<?php
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

if (!$request->isPost()) {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    die();
}

// CSRF protection
if (!check_bitrix_sessid()) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid session']);
    die();
}

\Bitrix\Main\Loader::includeModule('highloadblock');

function getHlClass(string $tableName): ?string
{
    $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList([
        'filter' => ['=TABLE_NAME' => $tableName],
    ])->fetch();

    if (!$hlblock) {
        return null;
    }

    $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
    return $entity->getDataClass();
}

$action = (string)$request->getPost('action');

// --- Загрузка квартир по дому ---
if ($action === 'get_apartments') {
    $buildingId = (int)$request->getPost('building_id');

    if (!$buildingId) {
        echo json_encode(['status' => 'error', 'message' => 'Не указан дом']);
        die();
    }

    $class = getHlClass('hl_apartments');
    if (!$class) {
        echo json_encode(['status' => 'error', 'message' => 'HL блок квартир не найден']);
        die();
    }

    $result = $class::getList([
        'filter' => [
            '=UF_BUILDING_ID' => $buildingId,
            '!UF_STATUS'      => 'SOLD',
        ],
        'select' => ['ID', 'UF_NUMBER', 'UF_STATUS'],
        'order'  => ['UF_NUMBER' => 'ASC'],
    ]);

    $apartments = [];
    while ($row = $result->fetch()) {
        $apartments[] = [
            'id'     => (int)$row['ID'],
            'number' => $row['UF_NUMBER'],
            'status' => $row['UF_STATUS'],
        ];
    }

    echo json_encode(['status' => 'ok', 'apartments' => $apartments]);
    die();
}

// --- Отправка заявки ---
if ($action === 'submit') {
    $name        = trim((string)$request->getPost('name'));
    $email       = trim((string)$request->getPost('email'));
    $phone       = trim((string)$request->getPost('phone'));
    $apartmentId = (int)$request->getPost('apartment_id');

    // Валидация
    $errors = [];

    if ($name === '') {
        $errors[] = 'Введите имя';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email';
    }

    if (!preg_match('/^\+?[\d\s\-\(\)]{7,20}$/', $phone)) {
        $errors[] = 'Введите корректный телефон';
    }

    if (!$apartmentId) {
        $errors[] = 'Выберите объект недвижимости';
    }

    if ($errors) {
        echo json_encode(['status' => 'error', 'message' => implode('. ', $errors)]);
        die();
    }

    $appClass = getHlClass('hl_applications');
    if (!$appClass) {
        echo json_encode(['status' => 'error', 'message' => 'HL блок заявок не найден']);
        die();
    }

    // Проверка дубля email на тот же объект
    $duplicate = $appClass::getList([
        'filter' => ['=UF_EMAIL' => $email, '=UF_APARTMENT_ID' => $apartmentId],
        'limit'  => 1,
    ])->fetch();

    if ($duplicate) {
        echo json_encode(['status' => 'error', 'message' => 'Такая почта уже есть по выбранному объекту']);
        die();
    }

    // Проверка дубля телефона на тот же объект
    $duplicate = $appClass::getList([
        'filter' => ['=UF_PHONE' => $phone, '=UF_APARTMENT_ID' => $apartmentId],
        'limit'  => 1,
    ])->fetch();

    if ($duplicate) {
        echo json_encode(['status' => 'error', 'message' => 'Такой телефон уже есть по выбранному объекту']);
        die();
    }

    // Проверка статуса квартиры
    $aptClass = getHlClass('hl_apartments');
    $apartment = $aptClass::getById($apartmentId)->fetch();

    if (!$apartment || $apartment['UF_STATUS'] === 'SOLD') {
        echo json_encode(['status' => 'error', 'message' => 'Выберите другой объект недвижимости']);
        die();
    }

    // Сохранение заявки
    $addResult = $appClass::add([
        'UF_NAME'         => $name,
        'UF_EMAIL'        => $email,
        'UF_PHONE'        => $phone,
        'UF_APARTMENT_ID' => $apartmentId,
        'UF_CREATED_AT'   => new \Bitrix\Main\Type\DateTime(),
    ]);

    if ($addResult->isSuccess()) {
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode([
            'status'  => 'error',
            'message' => implode(', ', $addResult->getErrorMessages()),
        ]);
    }
    die();
}

echo json_encode(['status' => 'error', 'message' => 'Неизвестный action']);
