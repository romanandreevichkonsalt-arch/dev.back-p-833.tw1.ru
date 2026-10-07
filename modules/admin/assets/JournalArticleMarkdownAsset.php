<?php

namespace app\modules\admin\assets;

use yii\web\AssetBundle;

class JournalArticleMarkdownAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/admin-journal-markdown.css',
    ];
    public $js = [
        'js/admin-journal-markdown.js',
    ];
    public $depends = [
        AdminAsset::class,
        EasyMdeAsset::class,
    ];
}
