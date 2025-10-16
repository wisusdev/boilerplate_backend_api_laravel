<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\TaxRate;
use App\Models\User;

class TaxRatePolicy
{
	/**
	 * Determine whether the user can view any tax rates.
	 */
	public function index(User $user): bool
	{
		return $user->can('tax-rates:index') ||
			$user->can('manage_business') ||
			$user->businesses()->exists();
	}

	/**
	 * Determine whether the user can view the tax rate.
	 */
	public function view(User $user, TaxRate $taxRate, Business $business): bool
	{
		return $user->can('tax-rates:show') ||
			$user->can('manage_business') ||
			$user->businesses()->exists();
	}

	/**
	 * Determine whether the user can create tax rates.
	 */
	public function create(User $user, Business $business): bool
	{
		return $user->can('tax-rates:create') ||
			$user->can('manage_business') ||
			$user->businesses()->exists();
	}

	/**
	 * Determine whether the user can update the tax rate.
	 */
	public function update(User $user, TaxRate $taxRate, Business $business): bool
	{
		return $user->can('tax-rates:update') ||
			$user->can('manage_business') ||
			$user->businesses()->exists();
	}

	/**
	 * Determine whether the user can delete the tax rate.
	 */
	public function delete(User $user, TaxRate $taxRate, Business $business): bool
	{
		return $user->can('tax-rates:delete') ||
			$user->can('manage_business') ||
			$user->businesses()->exists();
	}

	/**
	 * Determine whether the user can restore the tax rate.
	 */
	public function restore(User $user, TaxRate $taxRate, Business $business): bool
	{
		// El usuario puede restaurar tax rates de su propio negocio
		$userBusinessIds = $user->businesses->pluck('id')->toArray();

		return ($taxRate->business_id === $business->id) &&
			(in_array($business->id, $userBusinessIds) ||
				$user->can('manage_business') ||
				$business->owner_id === $user->id);
	}

	/**
	 * Determine whether the user can permanently delete the tax rate.
	 */
	public function forceDelete(User $user, TaxRate $taxRate, Business $business): bool
	{
		// El usuario puede eliminar permanentemente tax rates de su propio negocio
		$userBusinessIds = $user->businesses->pluck('id')->toArray();

		return ($taxRate->business_id === $business->id) &&
			(in_array($business->id, $userBusinessIds) ||
				$user->can('manage_business') ||
				$business->owner_id === $user->id);
	}
}
