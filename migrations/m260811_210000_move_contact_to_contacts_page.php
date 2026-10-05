<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_210000_move_contact_to_contacts_page extends Migration
{
    public function safeUp(): void
    {
        $contactsPageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'contacts'])
            ->scalar();

        if ($contactsPageId === false) {
            return;
        }

        $partnersPageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'partners'])
            ->scalar();

        $partnersContact = null;
        if ($partnersPageId !== false) {
            $partnersContactRow = (new Query())
                ->select(['data'])
                ->from('{{%content_blocks}}')
                ->where(['page_id' => $partnersPageId, 'block_key' => 'contact'])
                ->one();
            if ($partnersContactRow !== false) {
                $partnersContact = json_decode((string)$partnersContactRow['data'], true);
            }
        }

        $infoRow = (new Query())
            ->select(['id', 'data', 'sort_order'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $contactsPageId, 'block_key' => 'info'])
            ->one();

        $infoData = $infoRow !== false ? json_decode((string)$infoRow['data'], true) : null;
        if (!is_array($infoData)) {
            $infoData = [];
        }

        unset($infoData['formTitle'], $infoData['formSubtitle']);
        if ($infoRow !== false) {
            $this->update('{{%content_blocks}}', [
                'data' => json_encode($infoData, JSON_UNESCAPED_UNICODE),
            ], ['id' => $infoRow['id']]);
        }

        $contactExists = (new Query())
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $contactsPageId, 'block_key' => 'contact'])
            ->exists($this->db);

        if (!$contactExists) {
            $contactData = $this->buildContactData($partnersContact, $infoData);
            $infoSort = $infoRow !== false ? (int)$infoRow['sort_order'] : 2;
            $now = date('Y-m-d H:i:s');

            $this->insert('{{%content_blocks}}', [
                'page_id' => (int)$contactsPageId,
                'block_key' => 'contact',
                'block_type' => \app\services\content\BlockTypeRegistry::TYPE_CONTACT_CTA,
                'data' => json_encode($contactData, JSON_UNESCAPED_UNICODE),
                'sort_order' => $infoSort + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $pageIds = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => ['partners', 'designers', 'faq']])
            ->column();

        if ($pageIds !== []) {
            $this->delete('{{%content_blocks}}', [
                'block_key' => 'contact',
                'page_id' => $pageIds,
            ]);
        }
    }

    public function safeDown(): void
    {
        // Перенос contact не восстанавливается автоматически.
    }

    /**
     * @param array<string, mixed>|null $partnersContact
     * @param array<string, mixed> $infoData
     * @return array<string, mixed>
     */
    private function buildContactData(?array $partnersContact, array $infoData): array
    {
        $defaults = [
            'title' => 'Остались вопросы?',
            'subtitle' => 'Оставьте заявку — мы перезвоним',
            'image' => [
                'src' => 'https://dev.back-p-833.tw1.ru/images/contacts/form-photo.webp',
                'alt' => 'Салон',
            ],
            'privacyPolicyUrl' => 'https://dev.back-p-833.tw1.ru/files/privacy-policy.pdf',
        ];

        $title = trim((string)($infoData['formTitle'] ?? ''));
        $subtitle = trim((string)($infoData['formSubtitle'] ?? ''));

        if (is_array($partnersContact)) {
            if ($title === '') {
                $title = trim((string)($partnersContact['title'] ?? ''));
            }
            if ($subtitle === '') {
                $subtitle = trim((string)($partnersContact['subtitle'] ?? ''));
            }
            if (isset($partnersContact['image']) && is_array($partnersContact['image'])) {
                $defaults['image'] = $partnersContact['image'];
            }
            if (isset($partnersContact['privacyPolicyUrl'])) {
                $defaults['privacyPolicyUrl'] = $partnersContact['privacyPolicyUrl'];
            }
        }

        if ($title !== '') {
            $defaults['title'] = $title;
        }
        if ($subtitle !== '') {
            $defaults['subtitle'] = $subtitle;
        }

        return $defaults;
    }
}
