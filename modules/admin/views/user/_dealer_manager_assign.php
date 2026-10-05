<?php

use app\models\DealerManager;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var ActiveForm $form */
/** @var yii\base\Model $model */
/** @var DealerManager[] $managers */
/** @var string $attribute */

$attribute = $attribute ?? 'assigned_manager_id';
$createNewValue = '__create_new__';
$managerOptions = ['' => 'По умолчанию']
    + \yii\helpers\ArrayHelper::map($managers, 'id', 'name')
    + [$createNewValue => 'Создать нового…'];

echo $form->field($model, $attribute)->dropDownList($managerOptions);

$selectIdJs = Json::encode(Html::getInputId($model, $attribute));
$createNewValueJs = Json::encode($createNewValue);
$createUrlJs = Json::encode(Url::to(['/admin/dealer-manager/create-ajax']));

$this->registerJs(<<<JS
(function () {
    var selectId = {$selectIdJs};
    var createNewValue = {$createNewValueJs};
    var createUrl = {$createUrlJs};
    var select = document.getElementById(selectId);
    var modal = document.getElementById('dealer-manager-create-modal');
    var submitBtn = document.getElementById('dealer-manager-create-submit');
    var errorEl = document.getElementById('new-manager-error');
    if (!select || !modal || !submitBtn) return;

    var previousValue = select.value;

    function setModalOpen(isOpen) {
        modal.hidden = !isOpen;
        document.body.classList.toggle('admin-modal-open', isOpen);
    }

    select.addEventListener('change', function () {
        if (select.value === createNewValue) {
            select.value = previousValue;
            if (errorEl) {
                errorEl.hidden = true;
            }
            setModalOpen(true);
            return;
        }
        previousValue = select.value;
    });

    modal.querySelectorAll('[data-dealer-manager-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            setModalOpen(false);
        });
    });

    function readCsrfToken() {
        var paramEl = document.querySelector('meta[name="csrf-param"]');
        var tokenEl = document.querySelector('meta[name="csrf-token"]');
        if (!paramEl || !tokenEl) {
            return null;
        }
        return { param: paramEl.getAttribute('content'), token: tokenEl.getAttribute('content') };
    }

    function formatManagerErrors(errors) {
        if (!errors || typeof errors !== 'object') {
            return 'Не удалось создать менеджера.';
        }
        var messages = [];
        Object.keys(errors).forEach(function (key) {
            var value = errors[key];
            if (Array.isArray(value)) {
                messages = messages.concat(value);
            } else if (value) {
                messages.push(String(value));
            }
        });
        return messages.length ? messages.join(' ') : 'Не удалось создать менеджера.';
    }

    submitBtn.addEventListener('click', function () {
        var body = new FormData();
        var csrf = readCsrfToken();
        if (csrf) {
            body.append(csrf.param, csrf.token);
        }
        body.append('DealerManager[name]', document.getElementById('new-manager-name').value);
        body.append('DealerManager[phone]', document.getElementById('new-manager-phone').value);
        body.append('DealerManager[email]', document.getElementById('new-manager-email').value);
        [
            ['dayFrom', 'new-manager-hours-day-from'],
            ['dayTo', 'new-manager-hours-day-to'],
            ['timeFrom', 'new-manager-hours-time-from'],
            ['timeTo', 'new-manager-hours-time-to'],
        ].forEach(function (pair) {
            var el = document.getElementById(pair[1]);
            if (el) {
                body.append('WorkHours[' + pair[0] + ']', el.value);
            }
        });
        body.append('DealerManager[role_label]', document.getElementById('new-manager-role').value);
        body.append('DealerManager[is_active]', '1');
        fetch(createUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: csrf ? { 'X-CSRF-Token': csrf.token } : {},
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    if (!r.ok) {
                        throw new Error(formatManagerErrors(data && data.errors));
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (!data.ok) {
                    if (errorEl) {
                        errorEl.textContent = formatManagerErrors(data.errors);
                        errorEl.hidden = false;
                    }
                    return;
                }
                var createOpt = select.querySelector('option[value="' + createNewValue + '"]');
                var opt = document.createElement('option');
                opt.value = String(data.manager.id);
                opt.textContent = data.manager.name;
                opt.selected = true;
                if (createOpt) {
                    select.insertBefore(opt, createOpt);
                } else {
                    select.appendChild(opt);
                }
                previousValue = opt.value;
                setModalOpen(false);
            })
            .catch(function (err) {
                if (errorEl) {
                    errorEl.textContent = err && err.message ? err.message : 'Ошибка сети.';
                    errorEl.hidden = false;
                }
            });
    });
})();
JS);
