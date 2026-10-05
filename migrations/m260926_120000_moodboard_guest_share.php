<?php

use yii\db\Migration;

class m260926_120000_moodboard_guest_share extends Migration
{
    public function safeUp(): void
    {
        $this->dropForeignKey('fk_moodboards_author_user_id', '{{%moodboards}}');

        $this->alterColumn('{{%moodboards}}', 'author_user_id', $this->integer()->null());

        $this->addColumn('{{%moodboards}}', 'author_session_id', $this->string(64)->null()->after('author_user_id'));
        $this->addColumn('{{%moodboards}}', 'share_code', $this->string(64)->null()->after('public_id'));
        $this->addColumn(
            '{{%moodboards}}',
            'public_share_enabled',
            $this->boolean()->notNull()->defaultValue(false)->after('status'),
        );

        $this->addForeignKey(
            'fk_moodboards_author_user_id',
            '{{%moodboards}}',
            'author_user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->createIndex('uidx_moodboards_share_code', '{{%moodboards}}', 'share_code', true);
        $this->createIndex('idx_moodboards_session_updated', '{{%moodboards}}', ['author_session_id', 'updated_at']);

        $this->addColumn(
            '{{%guest_sessions}}',
            'moodboards_merged_at',
            $this->dateTime()->null()->after('cart_merged_at'),
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%guest_sessions}}', 'moodboards_merged_at');

        $this->dropIndex('idx_moodboards_session_updated', '{{%moodboards}}');
        $this->dropIndex('uidx_moodboards_share_code', '{{%moodboards}}');

        $this->delete('{{%moodboards}}', ['not', ['author_session_id' => null]]);

        $this->dropColumn('{{%moodboards}}', 'public_share_enabled');
        $this->dropColumn('{{%moodboards}}', 'share_code');
        $this->dropColumn('{{%moodboards}}', 'author_session_id');

        $this->dropForeignKey('fk_moodboards_author_user_id', '{{%moodboards}}');
        $this->alterColumn('{{%moodboards}}', 'author_user_id', $this->integer()->notNull());
        $this->addForeignKey(
            'fk_moodboards_author_user_id',
            '{{%moodboards}}',
            'author_user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }
}
