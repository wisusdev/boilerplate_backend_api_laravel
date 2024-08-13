<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings:index')->only('index');
        $this->middleware('can:settings:update')->only('update');
    }
    
    public function index(): SettingResource
    {
		$query = Setting::query()
			->allowedFilters(['key'])
			->sparseFieldset()
			->first();

		return SettingResource::make($query);
    }


	public function update(SettingRequest $request, Setting $setting): SettingResource
	{
		$dataValidated = $request->validated();
		$attributes = (object) $dataValidated['data']['attributes'];
		$settingsValue = json_decode($setting->value, true);

		$files = [];
		
		if ($dataValidated['data']['type'] === 'app') {
			$files = ['logo', 'favicon'];
		}

		foreach ($files as $file) {
			$fileApp = $attributes->$file;

			if (preg_match('/^data:image\/(\w+);base64,/', $fileApp)) {
				if (property_exists($attributes, $file) && $attributes->$file) {
					if (isset($settingsValue[$file]) && Storage::disk('public')->exists($settingsValue[$file])) {
						Storage::disk('public')->delete($settingsValue[$file]);
					}

					$fileApp = preg_replace('/^data:image\/\w+;base64,/', '', $fileApp);
					$fileApp = str_replace(' ', '+', $fileApp);
					$fileApp = base64_decode($fileApp);

					$image = new ImageManager(new Driver());
					$image = $image->read($fileApp)->toWebp(90);
					$fileName = config('app.destination_path') . '/' . $file . '.webp';
					Storage::disk('public')->put($fileName, (string) $image);

					$attributes->$file = $fileName;
				}
			}

			if($fileApp === null) {
				$attributes->$file = $settingsValue[$file] ?? null;
			}
		}

		$setting->update([
			'value' => json_encode($attributes)
		]);

		return SettingResource::make($setting);
	}
}
