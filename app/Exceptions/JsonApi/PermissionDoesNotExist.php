<?php

namespace App\Exceptions\JsonApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionDoesNotExist extends \Exception
{
    public function render(Request $request): JsonResponse
    {
		return response()->json([
			'errors' => [
				'title' => 'Permission does not exist',
				'detail' => $this->getMessage(),
				'status' => '409'
			]
		], 409);
    }
}