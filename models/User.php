<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

class User extends ActiveRecord implements IdentityInterface
{
    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_DEALER = 'dealer';

    public $password;
    public $authKey;
    public $accessToken;

    private static $users = [
        '100' => [
            'id' => '100',
            'username' => 'admin',
            'password' => 'admin',
            'authKey' => 'test100key',
            'accessToken' => '100-token',
        ],
        '101' => [
            'id' => '101',
            'username' => 'demo',
            'password' => 'demo',
            'authKey' => 'test101key',
            'accessToken' => '101-token',
        ],
    ];

    public static function tableName(): string
    {
        return '{{%users}}';
    }

    public function rules(): array
    {
        return [
            [['phone'], 'required', 'when' => static fn (self $model): bool => $model->isCustomer() && !$model->isDealer()],
            [['phone'], 'string', 'max' => 11],
            [['phone'], 'match', 'pattern' => '/^7\d{10}$/', 'when' => static fn (self $model): bool => $model->phone !== null && $model->phone !== ''],
            [['phone'], 'unique'],
            [['username'], 'string', 'max' => 255],
            [['username'], 'unique'],
            [['username'], 'required', 'when' => static fn (self $model): bool => $model->isDealer()],
            [['type'], 'in', 'range' => [self::TYPE_CUSTOMER, self::TYPE_DEALER]],
            [['password_hash'], 'string', 'max' => 255],
            [['is_blocked', 'subscription'], 'boolean'],
            [['subscription'], 'default', 'value' => false],
            [['profile_completed_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => \yii\behaviors\TimestampBehavior::class,
                'value' => static fn (): string => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public static function findIdentity($id)
    {
        $user = static::findOne(['id' => $id, 'is_blocked' => false]);
        if ($user !== null) {
            return $user;
        }

        return isset(self::$users[$id]) ? new static(self::$users[$id]) : null;
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        $tokenHash = hash('sha256', $token);
        $apiToken = ApiAccessToken::find()
            ->where(['token_hash' => $tokenHash, 'revoked_at' => null])
            ->andWhere(['>', 'expires_at', date('Y-m-d H:i:s')])
            ->one();

        if ($apiToken !== null) {
            $user = static::findOne(['id' => $apiToken->user_id, 'is_blocked' => false]);
            if ($user !== null) {
                return $user;
            }
        }

        foreach (self::$users as $legacyUser) {
            if ($legacyUser['accessToken'] === $token) {
                return new static($legacyUser);
            }
        }

        return null;
    }

    public static function findByUsername($username)
    {
        $user = static::find()->where(['username' => $username])->one();
        if ($user !== null) {
            return $user;
        }

        foreach (self::$users as $user) {
            if (strcasecmp($user['username'], $username) === 0) {
                return new static($user);
            }
        }

        return null;
    }

    public static function findDealerByUsername(string $username): ?self
    {
        return static::find()
            ->where(['username' => $username, 'type' => self::TYPE_DEALER])
            ->one();
    }

    public static function findByPhone(string $phone): ?self
    {
        return static::find()->where(['phone' => $phone])->one();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return $this->authKey ?? ('user-' . $this->getId());
    }

    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    public function validatePassword($password)
    {
        if ($this->password !== null) {
            return $this->password === $password;
        }

        if ($this->password_hash === null || $this->password_hash === '') {
            return false;
        }

        return \Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = \Yii::$app->security->generatePasswordHash($password);
    }

    public function isDealer(): bool
    {
        return $this->type === self::TYPE_DEALER;
    }

    public function isCustomer(): bool
    {
        return $this->type === self::TYPE_CUSTOMER || $this->type === null || $this->type === '';
    }

    public function isProfileComplete(): bool
    {
        if (!$this->isDealer()) {
            return true;
        }

        if ($this->profile_completed_at !== null) {
            return true;
        }

        $profile = $this->dealerProfile;

        return $profile !== null && $profile->isProfileComplete($this);
    }

    public function markProfileCompleteIfReady(): void
    {
        if (!$this->isDealer() || $this->profile_completed_at !== null) {
            return;
        }

        $profile = $this->dealerProfile;
        if ($profile === null || !$profile->isProfileComplete($this)) {
            return;
        }

        $this->profile_completed_at = date('Y-m-d H:i:s');
        $this->save(false, ['profile_completed_at', 'updated_at']);
    }

    public function getProfile()
    {
        return $this->hasOne(UserProfile::class, ['user_id' => 'id']);
    }

    public function getDealerProfile()
    {
        return $this->hasOne(DealerProfile::class, ['user_id' => 'id']);
    }

    public function getDisplayName(): string
    {
        if ($this->isDealer()) {
            $dealerProfile = $this->dealerProfile;
            if ($dealerProfile !== null) {
                if ($dealerProfile->manager_name !== null && trim($dealerProfile->manager_name) !== '') {
                    return trim($dealerProfile->manager_name);
                }

                if ($dealerProfile->company_name !== '') {
                    return $dealerProfile->company_name;
                }
            }
        }

        $profile = $this->profile;
        if ($profile !== null) {
            $name = $profile->display_name
                ?? trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return (string)($this->username ?: 'Пользователь #' . $this->id);
    }

    public function getFormattedPhone(): string
    {
        $phone = (string)$this->phone;
        if (strlen($phone) === 11 && str_starts_with($phone, '7')) {
            return sprintf(
                '+7 (%s) %s-%s-%s',
                substr($phone, 1, 3),
                substr($phone, 4, 3),
                substr($phone, 7, 2),
                substr($phone, 9, 2)
            );
        }

        return $phone !== '' ? $phone : '—';
    }
}
