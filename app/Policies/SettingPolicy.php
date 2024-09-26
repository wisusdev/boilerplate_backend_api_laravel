<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SettingPolicy
{
	public function index(User $user): bool
	{
		return $user->hasPermissionTo('settings:index');
	}
	public function app(User $user): bool
	{
		return $user->hasPermissionTo('settings:app');
	}

	public function taxes(User $user): bool
	{
		return $user->hasPermissionTo('settings:taxes');
	}

	public function shipping(User $user): bool
	{
		return $user->hasPermissionTo('settings:shipping');
	}

	public function socialAuthServices(User $user): bool
	{
		return $user->hasPermissionTo('settings:social_auth_services');
	}

	public function mail(User $user): bool
	{
		return $user->hasPermissionTo('settings:mail');
	}

	public function pushNotification(User $user): bool
	{
		return $user->hasPermissionTo('settings:push_notification');
	}

	public function paymentGateway(User $user): bool
	{
		return $user->hasPermissionTo('settings:payment_gateway');
	}
}
