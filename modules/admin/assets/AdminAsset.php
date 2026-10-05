<?php

namespace app\modules\admin\assets;

use yii\web\AssetBundle;

class AdminAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/admin-panel.css',
    ];
    public $js = [
        'js/admin-media-utils.js',
        'js/admin-dealer-access.js',
        'js/admin-media-folders.js',
        'js/admin-media-library.js',
        'js/admin-slug.js',
        'js/admin-media-picker.js',
        'js/admin-listing-tile-editor.js',
        'js/admin-product-gallery.js',
        'js/admin-model-fabrics.js',
        'js/admin-model-search-priority.js',
        'js/admin-fabric-colors.js',
        'js/admin-content-blocks.js',
        'js/admin-home-products.js',
        'js/admin-search-catalog-priority.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset',
    ];
}
