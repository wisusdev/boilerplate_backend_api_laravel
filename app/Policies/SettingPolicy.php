<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SettingPolicy
{
	public function app(User $user, Setting $setting): bool
	{
		return $user->hasPermissionTo('settings:app');
	}
}
