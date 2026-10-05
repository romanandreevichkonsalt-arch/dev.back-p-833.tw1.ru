<?php

namespace app\services\dealer;

use app\models\DealerPriceList;
use app\models\MediaFile;
use app\models\User;
use Yii;

final class DealerPriceListService
{
    /**
     * @return array{common: ?array<string, mixed>, personal: ?array<string, mixed>}
     */
    public function getPayloadForDealer(User $dealer): array
    {
        return [
            'common' => $this->resolveActivePayload(DealerPriceList::SCOPE_GLOBAL),
            'personal' => $this->resolveActivePayload(DealerPriceList::SCOPE_DEALER, (int)$dealer->id),
        ];
    }

    /**
     * @return array{url: string, label: string, filename: string, mimeType: ?string, updatedAt: ?string}|null
     */
    public function getOrderFormBlankPayload(): ?array
    {
        return $this->resolveActivePayload(DealerPriceList::SCOPE_ORDER_FORM);
    }

    /**
     * @return array{url: string, label: string, filename: string, mimeType: ?string, updatedAt: ?string}|null
     */
    private function resolveActivePayload(string $scope, ?int $dealerUserId = null): ?array
    {
        $query = DealerPriceList::find()
            ->where(['scope' => $scope, 'is_active' => true])
            ->with('mediaFile')
            ->orderBy(['updated_at' => SORT_DESC, 'id' => SORT_DESC]);

        if ($scope === DealerPriceList::SCOPE_DEALER) {
            $query->andWhere(['dealer_user_id' => $dealerUserId]);
        } else {
            $query->andWhere(['dealer_user_id' => null]);
        }

        $record = $query->one();

        return $record?->toApiPayload();
    }

    public function deactivatePrevious(string $scope, ?int $dealerUserId = null): void
    {
        $condition = ['scope' => $scope, 'is_active' => true];
        if ($scope === DealerPriceList::SCOPE_DEALER) {
            $condition['dealer_user_id'] = $dealerUserId;
        } else {
            $condition['dealer_user_id'] = null;
        }

        DealerPriceList::updateAll(['is_active' => false], $condition);
    }

    public function assignUploadedFile(
        string $scope,
        MediaFile $file,
        string $label,
        ?int $dealerUserId = null,
        ?int $uploadedByAdminId = null,
    ): DealerPriceList {
        $this->deactivatePrevious($scope, $dealerUserId);

        $record = new DealerPriceList([
            'scope' => $scope,
            'dealer_user_id' => $scope === DealerPriceList::SCOPE_DEALER ? $dealerUserId : null,
            'media_file_id' => (int)$file->id,
            'label' => $label !== '' ? $label : 'Прайс-лист',
            'is_active' => true,
            'uploaded_by_admin_id' => $uploadedByAdminId,
        ]);
        $record->save(false);

        return $record;
    }

    /**
     * Legacy fallback for params/media filename match.
     *
     * @return array{common: ?array<string, mixed>, personal: ?array<string, mixed>}
     */
    public function getPayloadWithLegacyFallback(User $dealer): array
    {
        $payload = $this->getPayloadForDealer($dealer);
        if ($payload['common'] === null) {
            $payload['common'] = $this->resolveLegacyGlobalPayload();
        }

        return $payload;
    }

    /**
     * Прайс для ЛК: индивидуальный, если есть; иначе общий (с legacy fallback).
     *
     * @return array{url: string, label: string, filename: string, mimeType: ?string, updatedAt: ?string}|null
     */
    public function resolveEffectiveForDealer(User $dealer): ?array
    {
        $payload = $this->getPayloadWithLegacyFallback($dealer);

        return $payload['personal'] ?? $payload['common'];
    }

    /**
     * @return array{url: string, label: string, filename: string, mimeType: ?string, updatedAt: ?string}|null
     */
    private function resolveLegacyGlobalPayload(): ?array
    {
        $config = Yii::$app->params['dealerPriceList'] ?? [];
        $label = trim((string)($config['label'] ?? 'Прайс-лист'));
        if ($label === '') {
            $label = 'Прайс-лист';
        }

        $file = $this->resolveLegacyMediaFile($config);
        if ($file === null) {
            $url = trim((string)($config['url'] ?? ''));
            if ($url === '') {
                return null;
            }

            return [
                'url' => $url,
                'label' => $label,
                'filename' => basename($url),
                'mimeType' => null,
                'updatedAt' => $this->nullableString($config['updatedAt'] ?? null),
            ];
        }

        return [
            'url' => $file->getPublicUrl(),
            'label' => $label,
            'filename' => (string)$file->filename,
            'mimeType' => $file->mime,
            'updatedAt' => $file->created_at,
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveLegacyMediaFile(array $config): ?MediaFile
    {
        $mediaFileId = (int)($config['mediaFileId'] ?? 0);
        if ($mediaFileId > 0) {
            $file = MediaFile::findOne(['id' => $mediaFileId, 'kind' => MediaFile::KIND_DOCUMENT]);
            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }
}
