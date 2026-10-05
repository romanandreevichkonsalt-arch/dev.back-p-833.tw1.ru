<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_180000_designers_materials_section extends Migration
{
    public function safeUp(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'designers'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $row = (new Query())
            ->select(['id', 'data'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'materials'])
            ->one();

        if ($row === false) {
            return;
        }

        $data = json_decode((string)$row['data'], true);
        if (!is_array($data)) {
            $data = [];
        }

        if (array_is_list($data)) {
            $data = $this->convertListToSection($data);
        }

        $data = $this->mergeDefaults($data);

        $type = \app\services\content\BlockTypeRegistry::detect('designers', 'materials', $data);

        $this->update('{{%content_blocks}}', [
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'block_type' => $type,
        ], ['id' => $row['id']]);
    }

    public function safeDown(): void
    {
        // Структура materials на designers не восстанавливается автоматически.
    }

    /**
     * @param array<int, array<string, mixed>> $list
     * @return array<string, mixed>
     */
    private function convertListToSection(array $list): array
    {
        $defaults = $this->defaultSection();
        $items = [];

        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = trim((string)($row['title'] ?? ''));
            $text = trim((string)($row['text'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }
            $items[] = array_filter([
                'title' => $title,
                'text' => $text,
            ], static fn (string $v): bool => $v !== '');
        }

        if ($items !== []) {
            $defaults['items'] = $items;
        }

        return $defaults;
    }

    /**
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function mergeDefaults(array $existing): array
    {
        $defaults = $this->defaultSection();

        foreach ($defaults as $key => $value) {
            if (!isset($existing[$key]) || $existing[$key] === '' || $existing[$key] === []) {
                $existing[$key] = $value;
            }
        }

        if (isset($existing['items']) && is_array($existing['items']) && $existing['items'] === []) {
            $existing['items'] = $defaults['items'];
        }

        return $existing;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSection(): array
    {
        return [
            'text' => 'Мы подготовили все необходимые цифровые материалы, чтобы интеграция нашей мебели в ваши проекты была максимально быстрой и точной',
            'archiveUrl' => 'https://dev.back-p-833.tw1.ru/files/designers-archive.zip',
            'archiveLabel' => 'Скачать полный архив',
            'image' => [
                'src' => 'https://dev.back-p-833.tw1.ru/images/designers/materials-bg.webp',
                'alt' => 'Материалы',
            ],
            'items' => [
                [
                    'title' => '3D-модели мебели',
                    'text' => 'Полный каталог 3D-моделей мебели в формате .max, оптимизированных для качественных рендеров',
                ],
                [
                    'title' => 'Библиотека текстур и материалов',
                    'text' => 'Примеры тканей, кож, деревянных и металлических элементов мебели в высоком разрешении с возможностью составлять мудборды',
                ],
                [
                    'title' => 'Техническая документация',
                    'text' => 'Спецификации, схемы сборки и чертежи в PDF для подготовки рабочих документов',
                ],
            ],
        ];
    }
}
