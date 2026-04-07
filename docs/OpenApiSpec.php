<?php

namespace app\docs;

use OpenApi\Annotations as OA;

/**
 * @OA\OpenApi(
 *     @OA\Server(
 *         url="/",
 *         description="Current API host"
 *     )
 * )
 *
 * @OA\Info(
 *     title="Fabrika API",
 *     version="1.0.0",
 *     description="Auto-generated API documentation"
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
