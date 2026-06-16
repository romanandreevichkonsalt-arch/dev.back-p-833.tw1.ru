<?php

namespace app\docs;

use OpenApi\Annotations as OA;

/**
 * @OA\OpenApi(
 *     @OA\Server(
 *         url="/",
 *         description="Текущий хост API"
 *     )
 * )
 *
 * @OA\Info(
 *     title="Fabrika API",
 *     version="1.0.0",
 *     description="Автоматически сгенерированная документация API"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/auth/request-code"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/auth/verify-code"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/auth/yandex"
 * )
 *
 * @OA\PathItem(
 *     path="/swagger/json-schema"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/ping"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/profile/me"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/categories"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/categories/tree"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/categories/{id}"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/categories/{id}/children"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/products"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/products/{id}"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/{path}"
 * )
 *
 * @OA\PathItem(
 *     path="/admin/default/index"
 * )
 *
 * @OA\Schema(
 *     schema="AuthRequestCodeInput",
 *     type="object",
 *     required={"phone"},
 *     @OA\Property(property="phone", type="string", example="79998886644")
 * )
 *
 * @OA\Schema(
 *     schema="AuthRequestCodeOutput",
 *     type="object",
 *     required={"phone","code","expires_in"},
 *     @OA\Property(property="phone", type="string", example="79998886644"),
 *     @OA\Property(property="code", type="string", example="123456"),
 *     @OA\Property(property="expires_in", type="integer", example=300)
 * )
 *
 * @OA\Schema(
 *     schema="AuthVerifyCodeInput",
 *     type="object",
 *     required={"phone","code"},
 *     @OA\Property(property="phone", type="string", example="79998886644"),
 *     @OA\Property(property="code", type="string", example="123456")
 * )
 *
 * @OA\Schema(
 *     schema="AuthYandexInput",
 *     type="object",
 *     required={"access_token"},
 *     @OA\Property(property="access_token", type="string", example="y0_AgAAA...")
 * )
 *
 * @OA\Schema(
 *     schema="AuthVerifyCodeOutput",
 *     type="object",
 *     required={"token_type","access_token","expires_in","user"},
 *     @OA\Property(property="token_type", type="string", example="Bearer"),
 *     @OA\Property(property="access_token", type="string", example="z4aMeWg3Hf..."),
 *     @OA\Property(property="expires_in", type="integer", example=2592000),
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         required={"id","phone"},
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="phone", type="string", example="79998886644")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PingResponse",
 *     type="object",
 *     required={"status","service","version"},
 *     @OA\Property(property="status", type="string", example="ok"),
 *     @OA\Property(property="service", type="string", example="fabrika-backend-api"),
 *     @OA\Property(property="version", type="string", example="v1")
 * )
 *
 * @OA\Schema(
 *     schema="ProfileMeResponse",
 *     type="object",
 *     required={"id","username","phone","profile"},
 *     @OA\Property(property="id", type="integer", example=100),
 *     @OA\Property(property="username", type="string", example="admin"),
 *     @OA\Property(property="phone", type="string", example="79998886644"),
 *     @OA\Property(
 *         property="profile",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="first_name", type="string", nullable=true, example="Иван"),
 *         @OA\Property(property="last_name", type="string", nullable=true, example="Иванов"),
 *         @OA\Property(property="display_name", type="string", nullable=true, example="Иван"),
 *         @OA\Property(property="email", type="string", nullable=true, example="ivan@example.com"),
 *         @OA\Property(property="avatar_url", type="string", nullable=true, example="https://avatars.yandex.net/get-yapic/123/islands-200"),
 *         @OA\Property(property="yandex_login", type="string", nullable=true, example="ivan-login")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     required={"total","page","per_page","page_count"},
 *     @OA\Property(property="total", type="integer", example=42),
 *     @OA\Property(property="page", type="integer", example=1),
 *     @OA\Property(property="per_page", type="integer", example=20),
 *     @OA\Property(property="page_count", type="integer", example=3)
 * )
 *
 * @OA\Schema(
 *     schema="Category",
 *     type="object",
 *     required={"id","name","slug","is_active","sort_order"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
 *     @OA\Property(property="name", type="string", example="Диваны и кресла"),
 *     @OA\Property(property="slug", type="string", example="divany-i-kresla"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="seo_title", type="string", nullable=true),
 *     @OA\Property(property="seo_description", type="string", nullable=true),
 *     @OA\Property(property="seo_h1", type="string", nullable=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="sort_order", type="integer", example=10),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="CategoryCreateRequest",
 *     type="object",
 *     required={"name","slug"},
 *     @OA\Property(property="parent_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="name", type="string", example="Угловые диваны"),
 *     @OA\Property(property="slug", type="string", example="uglovye-divany"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="seo_title", type="string", nullable=true),
 *     @OA\Property(property="seo_description", type="string", nullable=true),
 *     @OA\Property(property="seo_h1", type="string", nullable=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="sort_order", type="integer", example=20)
 * )
 *
 * @OA\Schema(
 *     schema="CategoryUpdateRequest",
 *     type="object",
 *     @OA\Property(property="parent_id", type="integer", nullable=true),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="seo_title", type="string", nullable=true),
 *     @OA\Property(property="seo_description", type="string", nullable=true),
 *     @OA\Property(property="seo_h1", type="string", nullable=true),
 *     @OA\Property(property="is_active", type="boolean"),
 *     @OA\Property(property="sort_order", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="CategoryListResponse",
 *     type="object",
 *     required={"items","meta"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Category")),
 *     @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
 * )
 *
 * @OA\Schema(
 *     schema="CategoryTreeNode",
 *     type="object",
 *     required={"id","name","slug","children"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="parent_id", type="integer", nullable=true),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="is_active", type="boolean"),
 *     @OA\Property(property="sort_order", type="integer"),
 *     @OA\Property(
 *         property="children",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/CategoryTreeNode")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CategoryTreeResponse",
 *     type="object",
 *     required={"items"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/CategoryTreeNode"))
 * )
 *
 * @OA\Schema(
 *     schema="ProductAttribute",
 *     type="object",
 *     required={"code","name","type","value"},
 *     @OA\Property(property="code", type="string", example="color"),
 *     @OA\Property(property="name", type="string", example="Цвет"),
 *     @OA\Property(property="type", type="string", enum={"string","number","bool"}),
 *     @OA\Property(property="value", oneOf={
 *         @OA\Schema(type="string"),
 *         @OA\Schema(type="number"),
 *         @OA\Schema(type="boolean")
 *     }, example="синий")
 * )
 *
 * @OA\Schema(
 *     schema="ProductCategoryRef",
 *     type="object",
 *     required={"id","name","slug"},
 *     @OA\Property(property="id", type="integer", example=2),
 *     @OA\Property(property="name", type="string", example="Прямые диваны"),
 *     @OA\Property(property="slug", type="string", example="pryamye-divany")
 * )
 *
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     required={"id","name","slug","sku","price","is_active","stock_qty"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Диван прямой Брера Букле Синий"),
 *     @OA\Property(property="slug", type="string", example="divan-pryamoy-brera-bukle-siniy"),
 *     @OA\Property(property="sku", type="string", example="SOFA-BRERA-BLUE"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="price", type="number", format="float", example=114990),
 *     @OA\Property(property="old_price", type="number", format="float", nullable=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="stock_qty", type="integer", example=5),
 *     @OA\Property(property="seo_title", type="string", nullable=true),
 *     @OA\Property(property="seo_description", type="string", nullable=true),
 *     @OA\Property(property="seo_h1", type="string", nullable=true),
 *     @OA\Property(property="categories", type="array", @OA\Items(ref="#/components/schemas/ProductCategoryRef")),
 *     @OA\Property(property="attributes", type="array", @OA\Items(ref="#/components/schemas/ProductAttribute")),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ProductAttributeInput",
 *     type="object",
 *     required={"code","value"},
 *     @OA\Property(property="code", type="string", example="color"),
 *     @OA\Property(property="value", oneOf={
 *         @OA\Schema(type="string"),
 *         @OA\Schema(type="number"),
 *         @OA\Schema(type="boolean")
 *     }, example="синий")
 * )
 *
 * @OA\Schema(
 *     schema="ProductCreateRequest",
 *     type="object",
 *     required={"name","slug","sku","price","category_ids"},
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="sku", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="price", type="number", format="float"),
 *     @OA\Property(property="old_price", type="number", format="float", nullable=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="stock_qty", type="integer", example=0),
 *     @OA\Property(property="seo_title", type="string", nullable=true),
 *     @OA\Property(property="seo_description", type="string", nullable=true),
 *     @OA\Property(property="seo_h1", type="string", nullable=true),
 *     @OA\Property(property="category_ids", type="array", @OA\Items(type="integer"), example={1,2}),
 *     @OA\Property(property="attributes", type="array", @OA\Items(ref="#/components/schemas/ProductAttributeInput"))
 * )
 *
 * @OA\Schema(
 *     schema="ProductUpdateRequest",
 *     type="object",
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="sku", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="price", type="number", format="float"),
 *     @OA\Property(property="old_price", type="number", format="float", nullable=true),
 *     @OA\Property(property="is_active", type="boolean"),
 *     @OA\Property(property="stock_qty", type="integer"),
 *     @OA\Property(property="seo_title", type="string", nullable=true),
 *     @OA\Property(property="seo_description", type="string", nullable=true),
 *     @OA\Property(property="seo_h1", type="string", nullable=true),
 *     @OA\Property(property="category_ids", type="array", @OA\Items(type="integer")),
 *     @OA\Property(property="attributes", type="array", @OA\Items(ref="#/components/schemas/ProductAttributeInput"))
 * )
 *
 * @OA\Schema(
 *     schema="ProductListResponse",
 *     type="object",
 *     required={"items","meta"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Product")),
 *     @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
 * )
 */
class OpenApiSpec
{
}
