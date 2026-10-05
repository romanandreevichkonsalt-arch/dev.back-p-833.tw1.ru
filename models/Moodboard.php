<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class Moodboard extends ActiveRecord
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public static function tableName(): string
    {
        return '{{%moodboards}}';
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
            [['public_id', 'title'], 'required'],
            [['author_user_id', 'cover_media_id', 'canvas_width', 'canvas_height'], 'integer'],
            [['public_id'], 'string', 'max' => 64],
            [['public_id'], 'unique'],
            [['author_session_id'], 'string', 'max' => 64],
            [['share_code'], 'string', 'max' => 64],
            [['share_code'], 'unique'],
            [['title'], 'string', 'max' => 255],
            [['status'], 'string', 'max' => 16],
            [['status'], 'in', 'range' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED]],
            [['public_share_enabled'], 'boolean'],
            [['author_user_id'], 'exist', 'skipOnEmpty' => true, 'targetClass' => User::class, 'targetAttribute' => ['author_user_id' => 'id']],
            [['cover_media_id'], 'exist', 'skipOnEmpty' => true, 'targetClass' => MediaFile::class, 'targetAttribute' => ['cover_media_id' => 'id']],
            [['author_user_id', 'author_session_id'], 'validateOwner'],
        ];
    }

    public function validateOwner(string $attribute): void
    {
        $hasUser = $this->author_user_id !== null && (int)$this->author_user_id > 0;
        $session = trim((string)($this->author_session_id ?? ''));
        $hasSession = $session !== '';

        if ($hasUser && $hasSession) {
            $this->addError($attribute, 'Укажите либо пользователя, либо гостевую сессию.');
        }
        if (!$hasUser && !$hasSession) {
            $this->addError($attribute, 'Укажите автора мудборда.');
        }
    }

    public function beforeValidate(): bool
    {
        if (!parent::beforeValidate()) {
            return false;
        }

        if ($this->public_id === null || $this->public_id === '') {
            $this->public_id = self::generatePublicId();
        }

        if ($this->public_share_enabled && ($this->share_code === null || $this->share_code === '')) {
            $this->share_code = self::generateShareCode();
        }

        return true;
    }

    public function getAuthor()
    {
        return $this->hasOne(User::class, ['id' => 'author_user_id']);
    }

    public function getCoverMedia()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'cover_media_id']);
    }

    public function getItems()
    {
        return $this->hasMany(MoodboardItem::class, ['moodboard_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getComments()
    {
        return $this->hasMany(MoodboardComment::class, ['moodboard_id' => 'id'])
            ->orderBy(['id' => SORT_ASC]);
    }

    public function isGuestOwned(): bool
    {
        $session = trim((string)($this->author_session_id ?? ''));

        return $session !== '';
    }

    public static function generatePublicId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function generateShareCode(): string
    {
        return bin2hex(random_bytes(12));
    }
}
