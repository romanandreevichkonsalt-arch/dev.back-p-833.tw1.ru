<?php

namespace app\modules\admin\models;

use app\models\PromoCodeTemplate;
use yii\base\Model;

class PromoCodeForm extends Model
{
    public string $code = '';
    public string $title = '';
    public string $type = PromoCodeTemplate::TYPE_CUSTOM;
    public float|string $discount_percent = 10;
    public bool $is_single_use = true;
    public bool $is_active = true;
    public string|null $valid_until = null;

    public function beforeValidate(): bool
    {
        if (!parent::beforeValidate()) {
            return false;
        }

        $this->code = mb_strtoupper(trim($this->code));

        if ($this->valid_until === '' || $this->valid_until === null) {
            $this->valid_until = null;
        } else {
            $this->valid_until = trim($this->valid_until);
        }

        return true;
    }

    public function rules(): array
    {
        return [
            [['code', 'title'], 'required'],
            [['code'], 'string', 'max' => 64],
            [['code'], 'match', 'pattern' => '/^[A-Za-z0-9_]+$/', 'message' => 'Только латиница, цифры и _.'],
            [['title'], 'string', 'max' => 255],
            [['discount_percent'], 'number', 'min' => 0, 'max' => 100],
            [['is_single_use', 'is_active'], 'boolean'],
            [['valid_until'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'code' => 'Код',
            'title' => 'Название',
            'type' => 'Тип',
            'discount_percent' => 'Скидка, %',
            'is_single_use' => 'Одноразовый',
            'is_active' => 'Активен',
            'valid_until' => 'Действует до',
        ];
    }

    public static function fromTemplate(PromoCodeTemplate $template): self
    {
        $form = new self();
        $form->code = $template->code;
        $form->title = $template->title;
        $form->type = $template->type;
        $form->discount_percent = $template->discount_percent;
        $form->is_single_use = (bool)$template->is_single_use;
        $form->is_active = (bool)$template->is_active;
        $form->valid_until = $template->valid_until !== null && $template->valid_until !== ''
            ? (string)$template->valid_until
            : null;

        return $form;
    }

    public function isEditableCode(): bool
    {
        return $this->type === PromoCodeTemplate::TYPE_CUSTOM;
    }
}
