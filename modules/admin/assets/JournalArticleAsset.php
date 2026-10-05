<?php

namespace app\modules\admin\assets;

use yii\web\AssetBundle;

class JournalArticleAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $js = [
        'js/admin-journal-article.js',
    ];
    public $depends = [
        AdminAsset::class,
    ];
}
