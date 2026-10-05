<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
/** @var bool $withSeo */

$withSeo = $withSeo ?? false;

echo $this->render('_block_hero_home', [
    'formData' => $formData,
    'withSeo' => $withSeo,
]);
