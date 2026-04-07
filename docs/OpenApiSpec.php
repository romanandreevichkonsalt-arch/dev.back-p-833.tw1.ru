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
 *     required={"id","username"},
 *     @OA\Property(property="id", type="integer", example=100),
 *     @OA\Property(property="username", type="string", example="admin")
 * )
 */
class OpenApiSpec
{
}
