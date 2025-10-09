<?php 

namespace App\Exceptions\JsonApi;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouteNotFoundException extends Exception
{
    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'errors' => [
                [
                    'title' => 'Route Not Found',
                    'detail' => $this->getMessage(),
                    'status' => '404'
                ]
            ]
        ], 404);
    }
}