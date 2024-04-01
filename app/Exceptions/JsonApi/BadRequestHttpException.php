<?php

namespace App\Exceptions\JsonApi;

use Exception;
use Illuminate\Http\Request;

class BadRequestHttpException extends Exception
{
    public function render(Request $request)
    {
        return response()->json([
            'errors' => [
                [
                    'title' => 'Bad Request',
                    'detail' => $this->getMessage(),
                    'status' => '400'
                ]
            ]
        ], 400);
    }
}
