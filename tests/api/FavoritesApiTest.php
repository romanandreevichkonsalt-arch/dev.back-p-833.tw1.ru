<?php

namespace tests\api;

use app\models\CatalogProduct;
use app\models\FavoriteItem;
use app\models\GuestSession;
use app\models\SmsCode;
use app\models\User;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

class FavoritesApiTest extends ApiTestCase
{
    private const TEST_PHONE = '79894232001';
    private const SESSION_ID = 'guest-session-fav-01';

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->user->setIdentity(null);
        Yii::$app->request->headers->remove('Authorization');
        Yii::$app->request->headers->remove('X-Session-ID');
        Yii::$app->request->setBodyParams([]);
        Yii::$app->request->setQueryParams([]);

        SmsCode::deleteAll(['phone' => self::TEST_PHONE]);
        $user = User::findByPhone(self::TEST_PHONE);
        if ($user !== null) {
            FavoriteItem::deleteAll(['user_id' => (int)$user->id]);
            $user->delete();
        }
        FavoriteItem::deleteAll(['session_id' => self::SESSION_ID]);
        GuestSession::deleteAll(['session_id' => self::SESSION_ID]);
        CatalogProduct::deleteAll(['like', 'slug', 'fav-api-test-%', false]);
    }

    public function testGuestAddListCheckRemove(): void
    {
        $product = $this->createProduct();
        $this->withSession(self::SESSION_ID);

        $added = $this->postJson('api/v1/favorites/add', [
            'productId' => $product->slug,
        ]);
        verify($added['productId'])->equals($product->slug);
        verify($added['isFavorite'])->true();

        $again = $this->postJson('api/v1/favorites/add', [
            'productId' => $product->slug,
        ]);
        verify($again['isFavorite'])->true();
        verify(FavoriteItem::find()->where(['session_id' => self::SESSION_ID])->count())->equals(1);

        $checked = $this->postJson('api/v1/favorites/check', [
            'productIds' => [$product->slug, 'missing-slug'],
        ]);
        verify($checked['favorites'][$product->slug])->true();
        verify($checked['favorites']['missing-slug'])->false();

        $list = $this->getJson('api/v1/favorites/list');
        verify($list['total'])->equals(1);
        verify($list['items'][0]['product']['slug'])->equals($product->slug);

        $removed = $this->postJson('api/v1/favorites/remove', [
            'productId' => $product->slug,
        ]);
        verify($removed['isFavorite'])->false();
        verify($this->getJson('api/v1/favorites/list')['total'])->equals(0);
    }

    public function testGuestRequiresSession(): void
    {
        $product = $this->createProduct();
        $this->expectException(UnauthorizedHttpException::class);
        $this->postJson('api/v1/favorites/add', [
            'productId' => $product->slug,
        ]);
    }

    public function testAddUnknownProduct(): void
    {
        $this->withSession(self::SESSION_ID);
        $this->expectException(NotFoundHttpException::class);
        $this->postJson('api/v1/favorites/add', [
            'productId' => 'does-not-exist-fav',
        ]);
    }

    public function testAuthMergesGuestFavorites(): void
    {
        $product = $this->createProduct();
        $this->withSession(self::SESSION_ID);
        $this->postJson('api/v1/favorites/add', [
            'productId' => $product->slug,
        ]);

        Yii::$app->request->headers->remove('Authorization');
        Yii::$app->user->setIdentity(null);

        $this->postJson('api/v1/auth/request-code', ['phone' => self::TEST_PHONE]);
        $smsCode = SmsCode::find()
            ->where(['phone' => self::TEST_PHONE, 'used_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();
        verify($smsCode)->notNull();

        $this->withSession(self::SESSION_ID);
        $tokenResponse = $this->postJson('api/v1/auth/verify-code', [
            'phone' => self::TEST_PHONE,
            'code' => $smsCode->code,
            'sessionId' => self::SESSION_ID,
        ]);

        verify($tokenResponse['guestSync']['skipped'])->false();
        verify($tokenResponse['guestSync']['favorites']['mergedCount'])->equals(1);
        verify($tokenResponse['guestSync']['favorites']['resultTotal'])->equals(1);

        Yii::$app->user->setIdentity(null);
        $this->withBearer($tokenResponse['access_token']);
        Yii::$app->request->headers->remove('X-Session-ID');

        $list = $this->getJson('api/v1/favorites/list');
        verify($list['total'])->equals(1);
        verify($list['items'][0]['product']['slug'])->equals($product->slug);

        $syncAgain = $this->postJson('api/v1/favorites/sync', [
            'sessionId' => self::SESSION_ID,
        ]);
        verify($syncAgain['mergedCount'])->equals(0);
        verify($syncAgain['resultTotal'])->equals(1);

        verify(FavoriteItem::find()->where(['session_id' => self::SESSION_ID])->count())->equals(0);
    }

    public function testAuthWithoutSessionSkipsMerge(): void
    {
        $this->postJson('api/v1/auth/request-code', ['phone' => self::TEST_PHONE]);
        $smsCode = SmsCode::find()
            ->where(['phone' => self::TEST_PHONE, 'used_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        $tokenResponse = $this->postJson('api/v1/auth/verify-code', [
            'phone' => self::TEST_PHONE,
            'code' => $smsCode->code,
        ]);

        verify($tokenResponse['guestSync']['skipped'])->true();
        verify($tokenResponse['guestSync']['reason'])->equals('no_session');
    }

    private function createProduct(): CatalogProduct
    {
        $product = new CatalogProduct([
            'slug' => 'fav-api-test-' . substr(md5(uniqid('', true)), 0, 10),
            'title' => 'Товар для избранного',
            'href' => '/product/fav-test',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        verify($product->save(false))->true();

        return $product;
    }
}
