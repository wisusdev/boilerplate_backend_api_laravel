<?php

namespace App\Exceptions\JsonApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleAlreadyExists extends \Exception
{
    public function render(Request $request): JsonResponse
    {
		return response()->json([
			'errors' => [
				'title' => 'Role already exists',
				'detail' => $this->getMessage(),
				'status' => '409'
			]
		], 409);
    }
}