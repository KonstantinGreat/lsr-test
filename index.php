<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

\Bitrix\Main\Loader::includeModule('highloadblock');

// Загружаем список домов
$buildings = [];
$hlBuildings = \Bitrix\Highloadblock\HighloadBlockTable::getList([
    'filter' => ['=TABLE_NAME' => 'hl_buildings'],
])->fetch();

if ($hlBuildings) {
    $entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlBuildings);
    $bClass  = $entity->getDataClass();
    $bResult = $bClass::getList([
        'select' => ['ID', 'UF_NAME'],
        'order'  => ['UF_NAME' => 'ASC'],
    ]);
    while ($row = $bResult->fetch()) {
        $buildings[] = $row;
    }
}
?>

<div style="max-width:520px;margin:40px auto;font-family:sans-serif;padding:0 16px">
    <h2>Заявка на объект недвижимости</h2>

    <div id="form-message" style="display:none;margin-bottom:16px;padding:12px;border-radius:4px;font-size:14px"></div>

    <form id="application-form" novalidate>
        <?= bitrix_sessid_post() ?>

        <div style="margin-bottom:14px">
            <label style="display:block;margin-bottom:4px;font-size:14px">Имя <span style="color:red">*</span></label>
            <input type="text" name="name" autocomplete="name"
                   style="width:100%;padding:8px;box-sizing:border-box;font-size:14px;border:1px solid #ccc;border-radius:4px">
        </div>

        <div style="margin-bottom:14px">
            <label style="display:block;margin-bottom:4px;font-size:14px">Email <span style="color:red">*</span></label>
            <input type="email" name="email" autocomplete="email"
                   style="width:100%;padding:8px;box-sizing:border-box;font-size:14px;border:1px solid #ccc;border-radius:4px">
        </div>

        <div style="margin-bottom:14px">
            <label style="display:block;margin-bottom:4px;font-size:14px">Телефон <span style="color:red">*</span></label>
            <input type="tel" name="phone" autocomplete="tel" placeholder="+7 (___) ___-__-__"
                   style="width:100%;padding:8px;box-sizing:border-box;font-size:14px;border:1px solid #ccc;border-radius:4px">
        </div>

        <div style="margin-bottom:14px">
            <label style="display:block;margin-bottom:4px;font-size:14px">Дом <span style="color:red">*</span></label>
            <select name="building_id" id="building-select"
                    style="width:100%;padding:8px;box-sizing:border-box;font-size:14px;border:1px solid #ccc;border-radius:4px">
                <option value="">— выберите дом —</option>
                <?php foreach ($buildings as $b): ?>
                    <option value="<?= (int)$b['ID'] ?>"><?= htmlspecialcharsbx($b['UF_NAME']) ?></option>
                <?php endforeach ?>
            </select>
        </div>

        <div style="margin-bottom:20px">
            <label style="display:block;margin-bottom:4px;font-size:14px">Квартира <span style="color:red">*</span></label>
            <select name="apartment_id" id="apartment-select" disabled
                    style="width:100%;padding:8px;box-sizing:border-box;font-size:14px;border:1px solid #ccc;border-radius:4px;color:#999">
                <option value="">— сначала выберите дом —</option>
            </select>
        </div>

        <button type="submit"
                style="padding:10px 28px;font-size:14px;cursor:pointer;background:#0055a5;color:#fff;border:none;border-radius:4px">
            Отправить заявку
        </button>
    </form>
</div>

<script>
(function () {
    var buildingSelect  = document.getElementById('building-select');
    var apartmentSelect = document.getElementById('apartment-select');
    var form            = document.getElementById('application-form');
    var msg             = document.getElementById('form-message');

    var statusLabels = { FREE: '', RESERVED: ' (забронирована)' };

    buildingSelect.addEventListener('change', function () {
        var buildingId = this.value;

        apartmentSelect.disabled = true;
        apartmentSelect.style.color = '#999';
        apartmentSelect.innerHTML = '<option value="">Загрузка...</option>';

        if (!buildingId) {
            apartmentSelect.innerHTML = '<option value="">— сначала выберите дом —</option>';
            return;
        }

        var params = new URLSearchParams({
            action:      'get_apartments',
            building_id: buildingId,
            sessid:      document.querySelector('[name="sessid"]').value,
        });

        fetch('/local/tools/form-handler.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    params.toString(),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.status === 'ok' && data.apartments.length) {
                apartmentSelect.innerHTML = '<option value="">— выберите квартиру —</option>';
                data.apartments.forEach(function (apt) {
                    var label = 'Кв. ' + apt.number + (statusLabels[apt.status] || '');
                    var opt = document.createElement('option');
                    opt.value       = apt.id;
                    opt.textContent = label;
                    apartmentSelect.appendChild(opt);
                });
                apartmentSelect.disabled    = false;
                apartmentSelect.style.color = '';
            } else {
                apartmentSelect.innerHTML = '<option value="">Нет доступных квартир</option>';
            }
        })
        .catch(function () {
            apartmentSelect.innerHTML = '<option value="">Ошибка загрузки</option>';
        });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var params = new URLSearchParams(new FormData(this));
        params.set('action', 'submit');

        fetch('/local/tools/form-handler.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    params.toString(),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            msg.style.display = 'block';

            if (data.status === 'ok') {
                msg.style.background = '#d4edda';
                msg.style.color      = '#155724';
                msg.textContent      = 'ok';
                form.reset();
                apartmentSelect.disabled    = true;
                apartmentSelect.style.color = '#999';
                apartmentSelect.innerHTML   = '<option value="">— сначала выберите дом —</option>';
            } else {
                msg.style.background = '#f8d7da';
                msg.style.color      = '#721c24';
                msg.textContent      = data.message;
            }

            msg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        })
        .catch(function () {
            msg.style.display    = 'block';
            msg.style.background = '#f8d7da';
            msg.style.color      = '#721c24';
            msg.textContent      = 'Ошибка соединения';
        });
    });
}());
</script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php'; ?>
