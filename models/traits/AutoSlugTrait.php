<?php

namespace app\models\traits;

use app\helpers\SlugHelper;

trait AutoSlugTrait
{
    /**
     * @return string Атрибут модели, из которого генерировать slug, если slug пуст.
     */
    protected function slugSourceAttribute(): string
    {
        return 'label';
    }

    protected function applyAutoSlug(): void
    {
        if (trim((string)$this->slug) === '') {
            $source = $this->slugSourceAttribute();
            $value = trim((string)$this->{$source});
            if ($value !== '') {
                $this->slug = SlugHelper::slugify($value);
            }
        }
    }

    public function beforeValidate(): bool
    {
        $this->applyAutoSlug();

        return parent::beforeValidate();
    }
}
