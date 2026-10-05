<?php

namespace app\services\moodboard;

use Yii;
use yii\web\UploadedFile;

final class MoodboardBoardRequestParser
{
    /**
     * @return array{payload: array<string, mixed>, coverFile: ?UploadedFile}
     */
    public static function parse(): array
    {
        $request = Yii::$app->request;
        $isMultipart = str_starts_with((string)$request->contentType, 'multipart/form-data');

        $payload = $isMultipart
            ? $request->post()
            : $request->getBodyParams();

        if (!is_array($payload)) {
            $payload = [];
        }

        foreach (['items', 'comments', 'canvas', 'cover'] as $key) {
            if (!isset($payload[$key]) || !is_string($payload[$key])) {
                continue;
            }
            $decoded = json_decode($payload[$key], true);
            if (is_array($decoded)) {
                $payload[$key] = $decoded;
            }
        }

        $coverFile = UploadedFile::getInstanceByName('cover');

        return [
            'payload' => $payload,
            'coverFile' => $coverFile,
        ];
    }
}
