<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PaypalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
	public function index(): JsonResource
	{
		$subscriptions = Subscription::query()
			->sparseFieldset()
			->jsonPaginate();

		return SubscriptionResource::collection($subscriptions);
	}

	public function show(Subscription $subscription): SubscriptionResource
	{
		return new SubscriptionResource($subscription);
	}

	public function store(SubscriptionRequest $request): SubscriptionResource|JsonResponse
	{
		try {
			DB::beginTransaction();
			$data = $request->validated();

			$package = Package::find($data['data']['attributes']['package_id']);
			$user = User::find($data['data']['attributes']['user_id']);
			$metadata = json_decode($package->metadata);

			$start_date = now();
			$trial_days = $package->trial_days;

			$end_date = match ($package->interval) {
				'day' => $start_date->addDays($package->interval_count),
				'week' => $start_date->addWeeks($package->interval_count),
				'month' => $start_date->addMonths($package->interval_count),
				'year' => $start_date->addYears($package->interval_count),
				default => $start_date,
			};

			if ($trial_days > 0) {
				$end_date = $start_date->addDays($package->trial_days);
			}

			$paymentMethod = $data['data']['attributes']['payment_method'];

			$subscription = Subscription::create([
				'user_id' => $data['data']['attributes']['user_id'],
				'package_id' => $package->id,
				'start_date' => $start_date,
				'end_date' => $end_date,
				'trial_ends_at' => $trial_days > 0 ? $end_date : null,
				'package_price' => $package->price,
				'package_details' => $package->description,
				'created_by' => $data['data']['attributes']['created_by'],
				'payment_method' => $paymentMethod,
			]);

			$paypalService = new PaypalService();

			$paypalSubscription = $paypalService->createSubscription(
				$package->id,
				$metadata->paypal_plan_id,
				$user->first_name . ' ' . $user->last_name,
				$user->email,
			);

			if ($paypalSubscription->http_code !== 201) {
				throw ValidationException::withMessages([
					'error' => ['errorAsOccurred']
				]);
			}

			$subscriptionLinks = collect($paypalSubscription->links);
			$approve = $subscriptionLinks->where('rel', 'approve')->first();

			DB::commit();

			if ($paymentMethod === 'paypal'){

				$subscription->update([
					'payment_transaction_id' => $paypalSubscription->id,
				]);

				return response()->json([
					'approve_url' => $approve->href,
				]);
			}

			return SubscriptionResource::make($subscription);

		} catch (\Exception $e) {
			DB::rollBack();
			throw ValidationException::withMessages([
				'error' => ['errorAsOccurred']
			]);
		}
	}

	public function update(Request $request, Subscription $subscription): SubscriptionResource
	{
		$data = $request->validate([
			'data' => ['required', 'array'],
			'data.type' => ['required', 'string', 'in:subscriptions'],
			'data.attributes.status' => ['in:approved,waiting,declined,cancel'],
		]);

		$subscription->update([
			'status' => $data['data']['attributes']['status'],
		]);

		return SubscriptionResource::make($subscription);
	}

	public function destroy(Subscription $subscription): void
	{
		$subscription->delete();
	}

	/**
	 * @throws ValidationException
	 */
	public function publicStore(SubscriptionRequest $request): JsonResponse
	{
		return $this->store($request);
	}

	public function validateSubscription(Request $request, Subscription $subscription)
	{
		dd($subscription);
	}
}
