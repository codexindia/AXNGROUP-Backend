<?php

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="AXN Group API Documentation",
 *      description="Agent-Leader-Admin System API Documentation with role-based access control",
 *      @OA\Contact(
 *          email="admin@axngroup.com"
 *      ),
 *      @OA\License(
 *          name="MIT",
 *          url="http://www.opensource.org/licenses/mit-license.php"
 *      )
 * )
 *
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description="Development API Server"
 * )
 *
 * @OA\SecurityScheme(
 *      securityScheme="bearerAuth",
 *      type="http",
 *      scheme="bearer",
 *      bearerFormat="JWT",
 *      description="Use Bearer token for authentication"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="Login and registration endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Wallet",
 *     description="Wallet management endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Shop",
 *     description="Shop onboarding endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Bank Transfer",
 *     description="Bank transfer management endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Profile",
 *     description="User profile management endpoints"
 * )
 */

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
