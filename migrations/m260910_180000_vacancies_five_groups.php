<?php

use app\modules\admin\helpers\ContentPageVacanciesHelper;
use yii\db\Migration;
use yii\helpers\Json;

class m260910_180000_vacancies_five_groups extends Migration
{
    public function safeUp(): void
    {
        $this->update('{{%vacancies}}', ['group_id' => 'client_experience'], ['group_id' => 'sales']);
        $this->update('{{%vacancies}}', ['group_id' => 'management'], ['group_id' => 'office']);

        $pageId = $this->db->createCommand(
            'SELECT id FROM {{%content_pages}} WHERE slug = :slug',
            [':slug' => 'vacancies']
        )->queryScalar();

        if ($pageId === false) {
            return;
        }

        $groups = ContentPageVacanciesHelper::defaultGroupTemplates();
        $this->update(
            '{{%content_blocks}}',
            ['data' => Json::encode($groups, JSON_UNESCAPED_UNICODE)],
            ['page_id' => (int)$pageId, 'block_key' => 'groups']
        );
    }

    public function safeDown(): void
    {
        $this->update('{{%vacancies}}', ['group_id' => 'sales'], ['group_id' => 'client_experience']);
        $this->update('{{%vacancies}}', ['group_id' => 'office'], ['group_id' => 'management']);

        $pageId = $this->db->createCommand(
            'SELECT id FROM {{%content_pages}} WHERE slug = :slug',
            [':slug' => 'vacancies']
        )->queryScalar();

        if ($pageId === false) {
            return;
        }

        $legacyGroups = [
            [
                'id' => 'production',
                'number' => '01',
                'title' => 'Производство и разработка',
                'description' => 'Мастера цеха, конструкторы и технологи.',
                'emptyTitle' => 'Сейчас у нас нет открытых вакансий',
                'emptyDescription' => 'Следите за обновлениями — новые позиции появятся здесь',
            ],
            [
                'id' => 'sales',
                'number' => '02',
                'title' => 'Продажи и сервис',
                'description' => 'Менеджеры, дизайнеры салонов и сервисные специалисты.',
                'emptyTitle' => 'Сейчас у нас нет открытых вакансий',
                'emptyDescription' => 'Следите за обновлениями — новые позиции появятся здесь',
            ],
            [
                'id' => 'office',
                'number' => '03',
                'title' => 'Офис и администрация',
                'description' => 'Специалисты офиса, маркетинга и управления.',
                'emptyTitle' => 'Сейчас у нас нет открытых вакансий',
                'emptyDescription' => 'Следите за обновлениями — новые позиции появятся здесь',
            ],
        ];

        $this->update(
            '{{%content_blocks}}',
            ['data' => Json::encode($legacyGroups, JSON_UNESCAPED_UNICODE)],
            ['page_id' => (int)$pageId, 'block_key' => 'groups']
        );
    }
}
