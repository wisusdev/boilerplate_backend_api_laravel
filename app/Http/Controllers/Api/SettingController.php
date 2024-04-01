<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $setting = Setting::all();
        return response()->json(['data' => $setting]);
    }

    public function show(string $key): JsonResponse
    {
        $setting = Setting::where('key', $key)->first();
        return response()->json(['data' => $setting]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'required|string'
        ]);

        $setting = Setting::where('key', $request->key)->first();
        $setting->value = $request->value;
        $setting->save();

        return response()->json(['message' => 'Setting updated successfully']);
    }
}
