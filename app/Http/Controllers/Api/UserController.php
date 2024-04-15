<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }
}
