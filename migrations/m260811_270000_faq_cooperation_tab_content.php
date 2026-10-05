<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_270000_faq_cooperation_tab_content extends Migration
{
    public function safeUp(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'faq'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $row = (new Query())
            ->select(['id', 'data'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => (int)$pageId, 'block_key' => 'categories'])
            ->one();

        if ($row === false) {
            return;
        }

        $data = json_decode((string)$row['data'], true);
        if (!is_array($data)) {
            $data = [];
        }

        $cooperationItems = $this->cooperationCategoryItems();
        $categories = [];

        foreach ($data as $cat) {
            if (!is_array($cat)) {
                continue;
            }

            if (($cat['id'] ?? '') === 'cooperation') {
                $cat['items'] = $cooperationItems;
            }

            $categories[] = $cat;
        }

        $this->update('{{%content_blocks}}', [
            'data' => json_encode($categories, JSON_UNESCAPED_UNICODE),
        ], ['id' => $row['id']]);
    }

    public function safeDown(): void
    {
        // Контент FAQ не восстанавливается автоматически.
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cooperationCategoryItems(): array
    {
        return [
            [
                'question' => 'Как открыть бизнес по франшизе мебельной фабрики «Анна»?',
                'paragraphs' => [
                    [
                        ['t' => 'Мы предлагаем запуск готового бизнеса под ключ без роялти и паушального взноса, с прогнозируемой ежемесячной прибылью от 350 000 рублей. Фабрика предоставляет проверенные инструменты, пошаговые стратегии старта, персональное сопровождение и маркетинговую поддержку на всех этапах. Узнать подробные условия и оставить заявку на франшизу можно в разделе «'],
                        ['t' => 'Сотрудничество', 'to' => '/partners', 's' => 'link'],
                        ['t' => '».'],
                    ],
                ],
            ],
            [
                'question' => 'Какие условия сотрудничества предусмотрены для дизайнеров интерьера?',
                'paragraphs' => [
                    'Мы приглашаем практикующих дизайнеров и интерьерные студии к долгосрочному партнёрству. Чтобы получить доступ к Личному кабинету дизайнера, достаточно отправить короткую заявку на регистрацию. В нём вы сможете полностью управлять партнёрской программой: видеть актуальные цены, использовать кэшбэк и промокоды, отслеживать статус выполнения заказов, а также сохранять избранные модели и скачивать их 3D-копии для своих проектов.',
                    [
                        ['t' => 'Подать заявку на регистрацию →', 'to' => '/designers', 's' => 'link'],
                    ],
                    [
                        ['t' => 'Войти в личный кабинет →', 'to' => '/designers', 's' => 'link'],
                    ],
                ],
            ],
            [
                'question' => 'Как стать оптовым покупателем?',
                'paragraphs' => [
                    'Для новых дилеров действуют комфортные условия старта: минимальный объём первой отгрузки составляет всего 5 единиц товара. Чтобы получить актуальный прайс-лист, каталог продукции и индивидуальное коммерческое предложение, просто заполните форму обратной связи внизу страницы.',
                ],
            ],
        ];
    }
}
