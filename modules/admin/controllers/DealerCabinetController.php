<?php

namespace app\modules\admin\controllers;

use app\models\DealerPriceList;
use app\models\MediaFile;
use app\models\MediaFolder;
use app\services\dealer\DealerPriceListService;
use app\services\media\LocalMediaStorage;
use Yii;
use yii\web\UploadedFile;

class DealerCabinetController extends BaseController
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('users');

        return true;
    }

    public function actionIndex(): \yii\web\Response
    {
        return $this->redirect(['/admin/user/index', 'tab' => 'dealers']);
    }

    public function actionUploadGlobalPriceList(): \yii\web\Response
    {
        $uploadedFile = UploadedFile::getInstanceByName('price_list_file');
        if ($uploadedFile === null) {
            Yii::$app->session->setFlash('error', 'Выберите файл прайс-листа.');
            return $this->redirect(['/admin/user/index', 'tab' => 'dealers']);
        }

        $label = trim((string)Yii::$app->request->post('label', 'Общий прайс-лист'));
        if ($label === '') {
            $label = 'Общий прайс-лист';
        }

        try {
            $storage = new LocalMediaStorage();
            $media = $storage->upload(
                $uploadedFile,
                null,
                MediaFile::KIND_DOCUMENT,
                null,
                MediaFolder::SLUG_DOCUMENTS,
            );
            (new DealerPriceListService())->assignUploadedFile(
                DealerPriceList::SCOPE_GLOBAL,
                $media,
                $label,
                null,
                Yii::$app->adminUser->id ?? null,
            );
            Yii::$app->session->setFlash('success', 'Общий прайс-лист загружен.');
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['/admin/user/index', 'tab' => 'dealers']);
    }
}
