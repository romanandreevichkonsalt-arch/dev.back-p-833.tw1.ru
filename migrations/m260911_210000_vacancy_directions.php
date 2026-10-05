<?php

use yii\db\Migration;

class m260911_210000_vacancy_directions extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%vacancy_directions}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'number' => $this->string(8)->notNull()->defaultValue(''),
            'title' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
            'empty_title' => $this->string(255)->notNull()->defaultValue('Сейчас у нас нет открытых вакансий'),
            'empty_description' => $this->string(255)->notNull()->defaultValue('Следите за обновлениями — новые позиции появятся здесь'),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $now = date('Y-m-d H:i:s');
        $templates = [
            ['production', '01', 'Производство и разработка', 'Мастера цеха, конструкторы и технологи — все, кто причастен к созданию наших предметов мебели.'],
            ['product_design', '02', 'Продукт и дизайн', 'Дизайнеры, проектировщики и специалисты по материалам — те, кто придумывает эстетику, форму и характер наших будущих коллекций.'],
            ['logistics', '03', 'Управление и логистика', 'Специалисты снабжения, логисты и специалисты склада — те, кто обеспечивает производство лучшим сырьём и бережно доставляет готовую мебель клиентам.'],
            ['client_experience', '04', 'Клиентский опыт', 'Консультанты шоурумов, менеджеры сервиса — все, кто помогает сделать путь к идеальному интерьеру лёгким и приятным.'],
            ['management', '05', 'Управление и администрация', 'Руководители направлений, операционные специалисты и управляющий персонал — те, кто координирует процессы и развивает команду.'],
        ];

        $directionIdsBySlug = [];
        foreach ($templates as $index => [$slug, $number, $title, $description]) {
            $this->insert('{{%vacancy_directions}}', [
                'slug' => $slug,
                'number' => $number,
                'title' => $title,
                'description' => $description,
                'sort_order' => $index,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $directionIdsBySlug[$slug] = (int)$this->db->getLastInsertID();
        }

        $this->addColumn('{{%vacancies}}', 'direction_id', $this->integer()->null()->after('group_id'));

        foreach ($directionIdsBySlug as $slug => $directionId) {
            $this->update('{{%vacancies}}', ['direction_id' => $directionId], ['group_id' => $slug]);
        }

        $fallbackId = $directionIdsBySlug['production'] ?? reset($directionIdsBySlug);
        if ($fallbackId !== false) {
            $this->update('{{%vacancies}}', ['direction_id' => $fallbackId], ['direction_id' => null]);
        }

        $this->alterColumn('{{%vacancies}}', 'direction_id', $this->integer()->notNull());
        $this->createIndex('idx_vacancies_direction', '{{%vacancies}}', ['direction_id', 'sort_order', 'id']);
        $this->addForeignKey(
            'fk_vacancies_direction',
            '{{%vacancies}}',
            'direction_id',
            '{{%vacancy_directions}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->dropIndex('idx_vacancies_group', '{{%vacancies}}');
        $this->dropColumn('{{%vacancies}}', 'group_id');
    }

    public function safeDown(): void
    {
        $this->addColumn('{{%vacancies}}', 'group_id', $this->string(32)->notNull()->defaultValue('production')->after('slug'));

        $rows = (new \yii\db\Query())
            ->from('{{%vacancy_directions}}')
            ->select(['id', 'slug'])
            ->all($this->db);

        foreach ($rows as $row) {
            $this->update('{{%vacancies}}', ['group_id' => $row['slug']], ['direction_id' => (int)$row['id']]);
        }

        $this->dropForeignKey('fk_vacancies_direction', '{{%vacancies}}');
        $this->dropIndex('idx_vacancies_direction', '{{%vacancies}}');
        $this->dropColumn('{{%vacancies}}', 'direction_id');
        $this->createIndex('idx_vacancies_group', '{{%vacancies}}', ['group_id', 'sort_order', 'id']);
        $this->dropTable('{{%vacancy_directions}}');
    }
}
