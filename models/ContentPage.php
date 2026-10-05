<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class ContentPage extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%content_pages}}';
    }

    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'value' => static fn (): string => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['slug', 'title'], 'required'],
            [['slug'], 'string', 'max' => 64],
            [['slug'], 'unique'],
            [['title'], 'string', 'max' => 255],
            [['is_active'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'slug' => 'Slug',
            'title' => 'Название',
            'is_active' => 'Активна',
        ];
    }

    public function getBlocks()
    {
        return $this->hasMany(ContentBlock::class, ['page_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public static function slugLabels(): array
    {
        return [
            'home' => 'Главная',
            'partners' => 'Франшиза',
            'designers' => 'Дизайнерам',
            'contacts' => 'Контакты',
            'faq' => 'FAQ',
            'journal' => 'Журнал',
            'building' => 'Производство',
            'about' => 'О нас',
            'vacancies' => 'Вакансии',
            'library' => 'Библиотека',
            'privacy-policy' => 'Политика конфиденциальности',
            'user-agreement' => 'Пользовательское соглашение',
        ];
    }

    /**
     * Страницы, видимые в админке «Страницы сайта», в порядке отображения.
     *
     * @return string[]
     */
    public static function adminIndexSlugs(): array
    {
        return array_keys(self::slugLabels());
    }

    /**
     * @param ContentPage[] $pages
     * @return ContentPage[]
     */
    public static function sortForAdminIndex(array $pages): array
    {
        $order = array_flip(self::adminIndexSlugs());

        usort($pages, static function (ContentPage $a, ContentPage $b) use ($order): int {
            $posA = $order[$a->slug] ?? PHP_INT_MAX;
            $posB = $order[$b->slug] ?? PHP_INT_MAX;

            if ($posA !== $posB) {
                return $posA <=> $posB;
            }

            return $a->id <=> $b->id;
        });

        return $pages;
    }

    public static function titleForSlug(string $slug): string
    {
        return self::slugLabels()[$slug] ?? ucfirst($slug);
    }
}
