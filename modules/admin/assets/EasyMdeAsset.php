<?php

namespace app\modules\admin\assets;

use yii\web\AssetBundle;

class EasyMdeAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'lib/easymde/easymde.min.css',
    ];
    public $js = [
        'lib/easymde/easymde.min.js',
    ];
    public $depends = [
        FontAwesomeAsset::class,
    ];
}
