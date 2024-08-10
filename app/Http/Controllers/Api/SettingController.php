<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings:index')->only('index');
        $this->middleware('can:settings:update')->only('update');
    }
    
    public function index(): JsonResponse
    {
		$query = Setting::query()
			->allowedFilters(['key'])
			->sparseFieldset()
			->first();

		return response()->json(['data' => json_decode($query->value, true)]);
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
