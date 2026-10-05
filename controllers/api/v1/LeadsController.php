<?php

namespace app\controllers\api\v1;

use app\exceptions\ApiValidationException;
use app\models\Lead;
use app\services\lead\LeadAttachmentUploadService;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\UploadedFile;

class LeadsController extends ApiController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['create', 'options'];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'create' => ['POST', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/leads",
     *     tags={"Заявки"},
     *     summary="Создание заявки с сайта",
     *     description="Типы (поле type): contacts — Контакты; faq — FAQ; partners — Партнер; designers — Дизайнер; vacancy — Вакансия. Для vacancy — multipart с attachment (резюме) или JSON без файла.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(ref="#/components/schemas/LeadCreateRequest")
     *         ),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"type","name","consent"},
     *                 allOf={
     *                     @OA\Schema(ref="#/components/schemas/LeadCreateRequest"),
     *                     @OA\Schema(
     *                         @OA\Property(
     *                             property="attachment",
     *                             type="string",
     *                             format="binary",
     *                             description="PDF, doc, docx, xls, xlsx (до 20 МБ). Часто для vacancy."
     *                         )
     *                     )
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Заявка создана",
     *         @OA\JsonContent(ref="#/components/schemas/LeadCreateResponse")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации", @OA\JsonContent(ref="#/components/schemas/ApiValidationError"))
     * )
     */
    public function actionCreate(): array
    {
        $lead = new Lead();
        $payload = Yii::$app->request->getBodyParams();
        if ($payload === [] && Yii::$app->request->isPost) {
            $payload = Yii::$app->request->post();
        }
        $lead->load($payload, '');

        if (!$lead->validate()) {
            throw new ApiValidationException('Ошибка валидации.', $lead->getErrors());
        }

        if (!$lead->save(false)) {
            throw new ApiValidationException('Не удалось сохранить заявку.');
        }

        $attachment = UploadedFile::getInstanceByName('attachment');
        if ($attachment !== null) {
            $upload = new LeadAttachmentUploadService();
            $saved = $upload->saveForLead($lead, $attachment);
            $lead->attachment_path = $saved['path'];
            $lead->attachment_original_name = $saved['originalName'];
            $lead->resume_name = $saved['originalName'];
            $lead->save(false, ['attachment_path', 'attachment_original_name', 'resume_name']);
        }

        $this->notifyManager($lead);

        Yii::$app->response->statusCode = 201;

        return [
            'ok' => true,
            'id' => (int)$lead->id,
        ];
    }

    private function notifyManager(Lead $lead): void
    {
        $notifyEmail = Yii::$app->params['leadsNotifyEmail'] ?? null;
        if ($notifyEmail === null || $notifyEmail === '') {
            return;
        }

        try {
            Yii::$app->mailer->compose()
                ->setTo($notifyEmail)
                ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
                ->setSubject('Новая заявка: ' . $lead->type)
                ->setTextBody(implode("\n", [
                    'Тип: ' . $lead->type,
                    'Имя: ' . $lead->name,
                    'Телефон: ' . $lead->phone,
                    'Email: ' . ($lead->email ?? '—'),
                    'Комментарий: ' . ($lead->comment ?? '—'),
                    'Студия: ' . ($lead->studio ?? '—'),
                    'Портфолио: ' . ($lead->portfolio ?? '—'),
                    'Город: ' . ($lead->city ?? '—'),
                    'Вакансия: ' . ($lead->vacancy_title ?? '—'),
                    'Slug вакансии: ' . ($lead->vacancy_slug ?? '—'),
                    'Файл: ' . ($lead->getAttachmentDisplayName() ?? '—'),
                ]))
                ->send();
        } catch (\Throwable $e) {
            Yii::warning('Lead notification failed: ' . $e->getMessage(), __METHOD__);
        }
    }
}
