<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

class User extends ActiveRecord implements IdentityInterface
{
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
            [['phone'], 'required'],
            [['phone'], 'string', 'max' => 11],
            [['phone'], 'match', 'pattern' => '/^7\d{10}$/'],
            [['phone'], 'unique'],
            [['username'], 'string', 'max' => 255],
            [['created_at', 'updated_at'], 'safe'],
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

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id)
    {
        $user = static::findOne($id);
        if ($user !== null) {
            return $user;
        }

        return isset(self::$users[$id]) ? new static(self::$users[$id]) : null;
    }

    /**
     * {@inheritdoc}
     */
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

        foreach (self::$users as $legacyUser) {
            if ($legacyUser['accessToken'] === $token) {
                return new static($legacyUser);
            }
        }

        return null;
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
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

    public static function findByPhone(string $phone): ?self
    {
        return static::find()->where(['phone' => $phone])->one();
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey()
    {
        return $this->authKey ?? ('user-' . $this->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        if ($this->password !== null) {
            return $this->password === $password;
        }

        // For phone-auth users password login is disabled by design.
        return false;
    }

    public function getProfile()
    {
        return $this->hasOne(UserProfile::class, ['user_id' => 'id']);
    }
}
