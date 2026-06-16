<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * @property int $id
 * @property string $phone
 * @property string|null $username
 * @property string|null $password_hash
 * @property string|null $auth_key
 * @property string $created_at
 * @property string $updated_at
 */
class User extends ActiveRecord implements IdentityInterface
{
    public $password;

    private static array $legacyUsers = [
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
            [['phone'], 'required'],
            [['phone'], 'string', 'max' => 11],
            [['phone'], 'match', 'pattern' => '/^7\d{10}$/'],
            [['phone'], 'unique'],
            [['username'], 'string', 'max' => 255],
            [['password_hash', 'auth_key'], 'string', 'max' => 255],
            [['created_at', 'updated_at'], 'safe'],
            [['password'], 'string', 'min' => 6],
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
        $user = static::findOne($id);
        if ($user !== null) {
            return $user;
        }

        return isset(self::$legacyUsers[$id]) ? new static(self::$legacyUsers[$id]) : null;
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        $tokenHash = hash('sha256', $token);
        $apiToken = ApiAccessToken::find()
            ->where(['token_hash' => $tokenHash, 'revoked_at' => null])
            ->andWhere(['>', 'expires_at', date('Y-m-d H:i:s')])
            ->one();

        if ($apiToken !== null) {
            return static::findIdentity($apiToken->user_id);
        }

        foreach (self::$legacyUsers as $legacyUser) {
            if ($legacyUser['accessToken'] === $token) {
                return new static($legacyUser);
            }
        }

        return null;
    }

    public static function findByUsername(string $username): ?self
    {
        return static::find()->where(['username' => $username])->one();
    }

    public static function findAdminByUsername(string $username): ?self
    {
        return static::find()
            ->where(['username' => $username])
            ->andWhere(['not', ['password_hash' => null]])
            ->andWhere(['<>', 'password_hash', ''])
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

    public function getAuthKey(): string
    {
        return $this->auth_key ?? ('user-' . $this->getId());
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->getAuthKey() === $authKey;
    }

    public function validatePassword(string $password): bool
    {
        if (!empty($this->password_hash)) {
            return Yii::$app->security->validatePassword($password, $this->password_hash);
        }

        if ($this->password !== null) {
            return $this->password === $password;
        }

        return false;
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function generateAuthKey(): void
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    public function hasAdminAccess(): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }

        $roles = Yii::$app->authManager->getRolesByUser((string) $this->id);

        return $roles !== [];
    }

    public function getProfile()
    {
        return $this->hasOne(UserProfile::class, ['user_id' => 'id']);
    }

    public function getRoleNames(): array
    {
        return array_keys(Yii::$app->authManager->getRolesByUser((string) $this->id));
    }
}
