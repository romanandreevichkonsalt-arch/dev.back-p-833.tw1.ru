<?php

namespace app\modules\admin\assets;

use yii\web\AssetBundle;

class FontAwesomeAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'lib/font-awesome/css/font-awesome.min.css',
    ];
}
