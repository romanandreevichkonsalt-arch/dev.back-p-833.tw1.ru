<?php

use yii\db\Migration;

class m260909_193000_create_vacancies_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%vacancies}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(128)->notNull()->unique(),
            'group_id' => $this->string(32)->notNull(),
            'title' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
            'salary' => $this->string(255)->null(),
            'salary_mobile' => $this->string(255)->null(),
            'department' => $this->string(255)->null(),
            'schedule' => $this->string(255)->null(),
            'location' => $this->string(255)->null(),
            'meta' => $this->string(255)->null(),
            'category_label' => $this->string(255)->null(),
            'posted_at' => $this->date()->null(),
            'requirements' => $this->text()->notNull(),
            'conditions' => $this->text()->notNull(),
            'seo_title' => $this->string(255)->null(),
            'seo_description' => $this->text()->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'show_on_about' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_vacancies_group', '{{%vacancies}}', ['group_id', 'sort_order', 'id']);
        $this->createIndex('idx_vacancies_active', '{{%vacancies}}', ['is_active']);

        $this->alterColumn('{{%leads}}', 'phone', $this->string(20)->null());
        $this->addColumn('{{%leads}}', 'vacancy_slug', $this->string(128)->null()->after('city'));
        $this->addColumn('{{%leads}}', 'vacancy_title', $this->string(255)->null()->after('vacancy_slug'));
        $this->addColumn('{{%leads}}', 'resume_name', $this->string(255)->null()->after('vacancy_title'));

        $now = date('Y-m-d H:i:s');
        $postedAt = '2026-06-27';

        $this->batchInsert('{{%vacancies}}', [
            'slug', 'group_id', 'title', 'description', 'salary', 'department', 'schedule', 'location',
            'posted_at', 'requirements', 'conditions', 'seo_title', 'seo_description',
            'sort_order', 'is_active', 'show_on_about', 'created_at', 'updated_at',
        ], [
            [
                'konstruktor-tehnolog-korpusa',
                'production',
                'Конструктор-технолог корпуса',
                'Работа со сложными узлами корпусной мебели.',
                'от 100 000 ₽',
                'Цех корпусной мебели',
                'Полный день',
                'Батайск',
                $postedAt,
                json_encode([
                    'Опыт работы на мебельном производстве от 1 года.',
                    'Умение читать чертежи и работать с технической документацией.',
                ], JSON_UNESCAPED_UNICODE),
                json_encode([
                    'Официальное трудоустройство и стабильная заработная плата.',
                    'Современное производство и профессиональное оборудование.',
                ], JSON_UNESCAPED_UNICODE),
                'Конструктор-технолог корпуса — вакансия | МФ Анна',
                'Вакансия конструктора-технолога корпуса на мебельной фабрике «Анна».',
                0,
                1,
                1,
                $now,
                $now,
            ],
            [
                'master-po-rabote-s-derevom',
                'production',
                'Мастер по работе с деревом',
                'Создание сложных узловых соединений для премиальной коллекции.',
                'от 100 000 ₽',
                'Цех корпусной мебели',
                'Полный день',
                'Батайск',
                $postedAt,
                json_encode([
                    'Опыт работы на мебельном производстве от 1 года.',
                    'Умение читать чертежи и работать с технической документацией.',
                ], JSON_UNESCAPED_UNICODE),
                json_encode([
                    'Официальное трудоустройство и стабильная заработная плата.',
                    'Современное производство и профессиональное оборудование.',
                ], JSON_UNESCAPED_UNICODE),
                'Мастер по работе с деревом — вакансия | МФ Анна',
                'Вакансия мастера по работе с деревом на мебельной фабрике «Анна».',
                1,
                1,
                1,
                $now,
                $now,
            ],
        ]);
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%leads}}', 'resume_name');
        $this->dropColumn('{{%leads}}', 'vacancy_title');
        $this->dropColumn('{{%leads}}', 'vacancy_slug');
        $this->alterColumn('{{%leads}}', 'phone', $this->string(20)->notNull());

        $this->dropTable('{{%vacancies}}');
    }
}
